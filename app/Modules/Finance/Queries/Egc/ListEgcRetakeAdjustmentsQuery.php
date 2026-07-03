<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Models\FinanceCharge;
use App\Modules\Finance\Support\EgcRetakeTargetResolver;
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
        $blocks = EgcBlock::where('semester_id', $semesterId)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->whereNotNull('finance_charge_id') // deferred blocks have no charge yet — skip
            ->when($campusId !== null, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)))
            ->with(['student:id,full_name,student_id,status', 'retakeDiscount'])
            ->get();

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

    private function findAvailableTargetCharges(EgcBlock $sourceBlock): Collection
    {
        if ($sourceBlock->student?->status !== 'intake_pre_uni_gc') {
            return collect();
        }

        $usedChargeIds = EgcRetakeDiscountLink::pluck('target_finance_charge_id')->toArray();

        $explicitTargets = EgcRetakeTargetResolver::targetBlocksFor($sourceBlock)
            ->filter(fn (EgcBlock $targetBlock): bool => (int) $targetBlock->level_number === (int) $sourceBlock->level_number
                && (bool) $targetBlock->is_retake)
            ->load([
                'semester:id,name',
                'financeCharge.invoiceLines.invoice:id,invoice_number,semester_id,status',
            ])
            ->filter(function (EgcBlock $targetBlock) use ($usedChargeIds): bool {
                $charge = $targetBlock->financeCharge;

                return $charge instanceof FinanceCharge
                    && $charge->status === FinanceCharge::STATUS_ACTIVE
                    && $charge->charge_type === FinanceCharge::TYPE_EGC_LEVEL_FEE
                    && ! in_array($charge->id, $usedChargeIds, true)
                    && $charge->invoiceLines->contains(fn ($line): bool => ($line->status ?? 'active') === 'active'
                        && ! in_array($line->invoice?->status, ['cancelled', 'void'], true));
            })
            ->map(function (EgcBlock $targetBlock) {
                $invoiceLine = $targetBlock->financeCharge?->invoiceLines
                    ->first(fn ($line) => ($line->status ?? 'active') === 'active'
                        && ! in_array($line->invoice?->status, ['cancelled', 'void'], true));

                return [
                    'id' => $targetBlock->financeCharge?->id,
                    'description' => $targetBlock->financeCharge?->description,
                    'amount' => $targetBlock->financeCharge?->amount,
                    'semester_id' => $targetBlock->semester_id,
                    'semester_name' => $targetBlock->semester?->name,
                    'invoice_id' => $invoiceLine?->invoice?->id,
                    'invoice_number' => $invoiceLine?->invoice?->invoice_number,
                    'target_block_number' => $targetBlock->block_number,
                    'target_level_number' => $targetBlock->level_number,
                ];
            });

        return $explicitTargets;
    }

    private function formatBlock(EgcBlock $block, array $targets): array
    {
        return [
            'id' => $block->id,
            'student_id' => $block->student_id,
            'student_name' => $block->student?->full_name,
            'student_code' => $block->student?->student_id,
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
