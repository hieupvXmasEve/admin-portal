<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Modules\Finance\Models\EgcRetakeDiscountLink;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Modules\Finance\Support\EgcRetakeTargetResolver;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use Illuminate\Support\Collection;

class ListEgcRetakeAdjustmentsQuery
{
    /**
     * Returns fail-result egc_blocks for the semester, grouped by status.
     *
     * Groups:
     * - eligible_with_targets: fail, attend ≥ 80%, no discount, targets available
     * - eligible_no_targets: fail, attend ≥ 80%, no discount, no targets available
     * - ineligible: fail, attend < 80%, no discount
     * - already_discounted: retake_discount_id not null
     */
    public function handle(int $semesterId, ?int $campusId = null): array
    {
        $blocks = collect(app(AcademicFinanceChargeSourceGateway::class)->failedEgcBlocksForSemester(
            semesterId: $semesterId,
            campusId: $campusId,
        ));
        $chargesByBlock = app(EgcBlockFinanceResolver::class)->chargesFor($blocks);
        $blocks = $blocks->filter(fn (AcademicEgcBlockData $block): bool => $chargesByBlock->has($block->id));

        $groups = [
            'eligible_with_targets' => [],
            'eligible_no_targets' => [],
            'ineligible' => [],
            'already_discounted' => [],
        ];

        foreach ($blocks as $block) {
            if ($block->retake_discount_id !== null) {
                $groups['already_discounted'][] = $this->formatBlock($block, []);

                continue;
            }

            $attendanceRate = (float) ($block->attendance_rate ?? 0);

            if ($attendanceRate < 80) {
                $groups['ineligible'][] = $this->formatBlock($block, []);

                continue;
            }

            // Eligible: find available target charges
            $targets = $this->findAvailableTargetCharges($block);

            if ($targets->isEmpty()) {
                $groups['eligible_no_targets'][] = $this->formatBlock($block, []);
            } else {
                $groups['eligible_with_targets'][] = $this->formatBlock($block, $targets->toArray());
            }
        }

        return $groups;
    }

    private function findAvailableTargetCharges(AcademicEgcBlockData $sourceBlock): Collection
    {
        if ($sourceBlock->student_status !== 'intake_pre_uni_gc') {
            return collect();
        }

        $usedLineIds = EgcRetakeDiscountLink::pluck('target_invoice_line_id')->toArray();

        $explicitTargets = EgcRetakeTargetResolver::targetBlocksFor($sourceBlock)
            ->filter(fn (AcademicEgcBlockData $targetBlock): bool => (int) $targetBlock->level_number === (int) $sourceBlock->level_number
                && (bool) $targetBlock->is_retake);
        $chargesByBlock = app(EgcBlockFinanceResolver::class)->chargesFor($explicitTargets);

        return $explicitTargets
            ->filter(function (AcademicEgcBlockData $targetBlock) use ($usedLineIds, $chargesByBlock): bool {
                $charge = $chargesByBlock->get($targetBlock->id);
                $invoiceLines = $charge?->invoiceLines()->with('invoice:id,invoice_number,semester_id,status')->get() ?? collect();

                return $charge instanceof FinanceCharge
                    && $charge->status === FinanceCharge::STATUS_ACTIVE
                    && $charge->charge_type === FinanceCharge::TYPE_EGC_LEVEL_FEE
                    && $invoiceLines->contains(fn ($line): bool => ! in_array($line->id, $usedLineIds, true)
                        && ($line->status ?? 'active') === 'active'
                        && ! in_array($line->invoice?->status, ['cancelled', 'void'], true));
            })
            ->map(function (AcademicEgcBlockData $targetBlock) use ($chargesByBlock) {
                $charge = $chargesByBlock->get($targetBlock->id);
                $invoiceLine = $charge?->invoiceLines()
                    ->with('invoice:id,invoice_number,semester_id,status')
                    ->get()
                    ->first(fn ($line) => ($line->status ?? 'active') === 'active'
                        && ! in_array($line->invoice?->status, ['cancelled', 'void'], true));

                return [
                    'id' => $charge?->id,
                    'description' => $charge?->description,
                    'amount' => $charge?->amount,
                    'semester_id' => $targetBlock->semester_id,
                    'semester_name' => $targetBlock->semester_name,
                    'invoice_id' => $invoiceLine?->invoice?->id,
                    'invoice_number' => $invoiceLine?->invoice?->invoice_number,
                    'target_block_number' => $targetBlock->block_number,
                    'target_level_number' => $targetBlock->level_number,
                ];
            });
    }

    private function formatBlock(AcademicEgcBlockData $block, array $targets): array
    {
        return [
            'id' => $block->id,
            'student_id' => $block->student_id,
            'student_name' => $block->student_name,
            'student_code' => $block->student_code,
            'block_number' => $block->block_number,
            'level_number' => $block->level_number,
            'result' => $block->result,
            'attendance_rate' => $block->attendance_rate,
            'is_retake' => $block->is_retake,
            'retake_discount_id' => $block->retake_discount_id,
            'available_targets' => $targets,
        ];
    }
}
