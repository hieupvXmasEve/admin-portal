<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\DiscountAllocation;
use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\EgcLevelFeeResolver;
use Illuminate\Support\Facades\DB;

class ReconcileEgcChargesAfterSyncAction
{
    /**
     * Reconcile future pending EGC blocks/charges after a synced semester proves a failed level.
     *
     * @return array{
     *     students_considered:int,
     *     students_reconciled:int,
     *     releveled_blocks:int,
     *     discounts_applied:int,
     *     settlement_releases:int,
     *     skipped:array<int, array<string, mixed>>,
     *     needs_manual_repair:array<int, array<string, mixed>>
     * }
     */
    public static function run(int $semesterId, ?array $studentIds = null): array
    {
        $summary = self::emptySummary();

        $sourceBlocks = EgcBlock::query()
            ->where('semester_id', $semesterId)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->whereNotNull('finance_charge_id')
            ->whereNull('retake_discount_id')
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->with('student:id,gc_total_levels,student_id')
            ->orderBy('student_id')
            ->orderBy('block_number')
            ->get();

        $summary['students_considered'] = $sourceBlocks->pluck('student_id')->unique()->count();
        $reconciledStudentIds = [];

        foreach ($sourceBlocks as $sourceBlock) {
            $targets = self::targetBlocksFor($sourceBlock);

            if ($targets->isEmpty()) {
                $summary['skipped'][] = self::row($sourceBlock, 'no_pending_target_blocks');

                continue;
            }

            $expectedLevels = [];
            foreach ($targets->values() as $index => $targetBlock) {
                $expectedLevels[(int) $targetBlock->id] = (int) $sourceBlock->level_number + $index;
            }

            $stopReason = self::firstStopReason($sourceBlock, $targets, $expectedLevels);
            if ($stopReason !== null) {
                $summary['needs_manual_repair'][] = self::row($sourceBlock, $stopReason);

                continue;
            }

            $result = DB::transaction(function () use ($sourceBlock, $targets, $expectedLevels): array {
                $innerSummary = [
                    'releveled_blocks' => 0,
                    'discounts_applied' => 0,
                    'settlement_releases' => 0,
                ];

                foreach ($targets as $targetBlock) {
                    $expectedLevel = $expectedLevels[(int) $targetBlock->id];
                    $isRetake = $expectedLevel === (int) $sourceBlock->level_number;

                    if ((int) $targetBlock->level_number === $expectedLevel && (bool) $targetBlock->is_retake === $isRetake) {
                        continue;
                    }

                    $innerSummary['settlement_releases'] += self::relevelTargetBlock($targetBlock, $expectedLevel, $isRetake);
                    $innerSummary['releveled_blocks']++;
                }

                if ((float) ($sourceBlock->attendance_rate ?? 0) >= 80.0) {
                    $retakeTarget = $targets->first(
                        fn (EgcBlock $targetBlock): bool => $expectedLevels[(int) $targetBlock->id] === (int) $sourceBlock->level_number
                    );

                    if ($retakeTarget instanceof EgcBlock && $sourceBlock->fresh()->retake_discount_id === null) {
                        ApplyEgcRetakeDiscountAction::run((int) $sourceBlock->id, (int) $retakeTarget->finance_charge_id);
                        $innerSummary['discounts_applied']++;
                    }
                }

                return $innerSummary;
            });

            $summary['releveled_blocks'] += $result['releveled_blocks'];
            $summary['discounts_applied'] += $result['discounts_applied'];
            $summary['settlement_releases'] += $result['settlement_releases'];

            if ($result['releveled_blocks'] > 0 || $result['discounts_applied'] > 0) {
                $reconciledStudentIds[(int) $sourceBlock->student_id] = true;
            }
        }

        $summary['students_reconciled'] = count($reconciledStudentIds);

        return $summary;
    }

    private static function relevelTargetBlock(EgcBlock $targetBlock, int $expectedLevel, bool $isRetake): int
    {
        $charge = FinanceCharge::query()->findOrFail($targetBlock->finance_charge_id);
        $line = self::activeInvoiceLineFor($charge);
        $amount = app(EgcLevelFeeResolver::class)->resolve($expectedLevel);
        $description = "EGC Level {$expectedLevel} Fee";

        $targetBlock->update([
            'level_number' => $expectedLevel,
            'is_retake' => $isRetake,
        ]);

        $charge->update([
            'amount' => $amount,
            'description' => $description,
        ]);

        $line->update([
            'amount_snapshot' => $amount,
            'description_snapshot' => $description,
        ]);

        $released = app(SettlementService::class)->releaseLineOverpayment(
            $line->fresh(),
            auth()->id(),
            self::class,
            (int) $targetBlock->id,
        );

        $line->invoice()->first()?->recalculateTotals();

        return (int) $released['count'];
    }

    private static function firstStopReason(EgcBlock $sourceBlock, iterable $targets, array $expectedLevels): ?string
    {
        $studentTotalLevels = (int) ($sourceBlock->student?->gc_total_levels ?? 0);

        foreach ($targets as $targetBlock) {
            $expectedLevel = $expectedLevels[(int) $targetBlock->id];
            if ($studentTotalLevels > 0 && $expectedLevel > $studentTotalLevels) {
                return 'expected_level_exceeds_student_total_levels';
            }

            if ($targetBlock->result !== EgcBlock::RESULT_PENDING) {
                return 'target_block_not_pending';
            }

            $charge = $targetBlock->financeCharge;
            if (! $charge instanceof FinanceCharge || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
                return 'target_charge_not_active';
            }

            $activeLines = self::activeInvoiceLinesFor($charge);
            if ($activeLines->count() !== 1) {
                return 'target_charge_requires_exactly_one_active_invoice_line';
            }

            $invoice = $activeLines->first()?->invoice;
            if (! $invoice instanceof StudentInvoice || in_array($invoice->status, ['cancelled', 'void'], true)) {
                return 'target_invoice_not_reconcilable';
            }

            if (EgcRetakeDiscountLink::query()->where('target_finance_charge_id', $charge->id)->exists()) {
                return 'target_charge_already_used_for_retake_discount';
            }

            if (self::hasNonRetakeDiscountAllocation($activeLines->first())) {
                return 'target_line_has_non_retake_discount';
            }
        }

        return null;
    }

    private static function targetBlocksFor(EgcBlock $sourceBlock)
    {
        return EgcBlock::query()
            ->where('student_id', $sourceBlock->student_id)
            ->where('result', EgcBlock::RESULT_PENDING)
            ->whereNotNull('finance_charge_id')
            ->where(function ($query) use ($sourceBlock): void {
                $query->where('semester_id', '>', $sourceBlock->semester_id)
                    ->orWhere(function ($sameSemesterQuery) use ($sourceBlock): void {
                        $sameSemesterQuery->where('semester_id', $sourceBlock->semester_id)
                            ->where('block_number', '>', $sourceBlock->block_number);
                    });
            })
            ->with(['financeCharge.invoiceLines.invoice'])
            ->orderBy('semester_id')
            ->orderBy('block_number')
            ->get();
    }

    private static function activeInvoiceLineFor(FinanceCharge $charge): InvoiceLine
    {
        return self::activeInvoiceLinesFor($charge)->firstOrFail();
    }

    private static function activeInvoiceLinesFor(FinanceCharge $charge)
    {
        return $charge->invoiceLines()
            ->where('status', 'active')
            ->with('invoice')
            ->get();
    }

    private static function hasNonRetakeDiscountAllocation(?InvoiceLine $line): bool
    {
        if (! $line instanceof InvoiceLine) {
            return true;
        }

        return DiscountAllocation::query()
            ->where('invoice_line_id', $line->id)
            ->where('amount', '>', 0)
            ->whereHas('invoiceDiscount', function ($query): void {
                $query->where('discount_type', '!=', ApplyEgcRetakeDiscountAction::DISCOUNT_TYPE)
                    ->where('status', '!=', 'reversed');
            })
            ->exists();
    }

    private static function emptySummary(): array
    {
        return [
            'students_considered' => 0,
            'students_reconciled' => 0,
            'releveled_blocks' => 0,
            'discounts_applied' => 0,
            'settlement_releases' => 0,
            'skipped' => [],
            'needs_manual_repair' => [],
        ];
    }

    private static function row(EgcBlock $sourceBlock, string $reason): array
    {
        return [
            'student_id' => (int) $sourceBlock->student_id,
            'student_code' => $sourceBlock->student?->student_id,
            'source_egc_block_id' => (int) $sourceBlock->id,
            'reason' => $reason,
        ];
    }
}
