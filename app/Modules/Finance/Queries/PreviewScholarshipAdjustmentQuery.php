<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentPreview;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentPreviewReader;

/**
 * Money projection for a proposed scholarship adjustment — read-only.
 *
 * Deliberately routes every number through ScholarshipDiscountResolver (the
 * same object the ledger writes with) instead of recomputing the discount, so
 * the figure staff approve and the student sees is the figure that will be
 * charged, including its clamp to the tuition base.
 */
class PreviewScholarshipAdjustmentQuery implements ScholarshipAdjustmentPreviewReader
{
    public function __construct(private readonly ScholarshipDiscountResolver $resolver) {}

    public function preview(int $studentId, int $targetSemesterId, ?float $proposedAmount): ScholarshipAdjustmentPreview
    {
        $award = StudentScholarshipAward::query()
            ->where('student_id', $studentId)
            ->with('scholarshipDefinition')
            ->first();

        $definition = $award?->scholarshipDefinition;

        // Same invoice selection rule as ScholarshipAdjustmentTimingGuard: the
        // latest invoice for the semester carrying an active tuition_term line.
        $invoice = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $targetSemesterId)
            ->whereHas('invoiceLines', function ($query): void {
                $query->where('status', 'active')
                    ->whereHas('charge', fn ($charge) => $charge
                        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                        ->where('status', FinanceCharge::STATUS_ACTIVE));
            })
            ->latest('id')
            ->first();

        if ($invoice === null || $definition === null) {
            return new ScholarshipAdjustmentPreview(
                has_invoice: false,
                tuition_base: 0.0,
                original_type: $definition?->type,
                original_amount: $definition !== null ? (float) $definition->amount : null,
                current_discount: 0.0,
                adjusted_discount: null,
                payable_before: 0.0,
                payable_after: null,
            );
        }

        $base = $this->resolver->invoiceTuitionBase($invoice);
        $currentDiscount = $this->resolver->resolve($definition, $base);

        $adjustedDiscount = null;

        if ($proposedAmount !== null) {
            // An unsaved proposal: build the same shape resolveAdjusted() reads
            // so the preview and the eventual apply share one code path. The
            // model is never persisted.
            $proposal = new ScholarshipSemesterAdjustment([
                'original_type' => $definition->type,
                'adjusted_amount' => $proposedAmount,
            ]);

            $adjustedDiscount = $this->resolver->resolveAdjusted($definition, $base, $proposal);
        }

        return new ScholarshipAdjustmentPreview(
            has_invoice: true,
            tuition_base: round($base, 2),
            original_type: $definition->type,
            original_amount: (float) $definition->amount,
            current_discount: round($currentDiscount, 2),
            adjusted_discount: $adjustedDiscount === null ? null : round($adjustedDiscount, 2),
            payable_before: round($base - $currentDiscount, 2),
            payable_after: $adjustedDiscount === null ? null : round($base - $adjustedDiscount, 2),
        );
    }
}
