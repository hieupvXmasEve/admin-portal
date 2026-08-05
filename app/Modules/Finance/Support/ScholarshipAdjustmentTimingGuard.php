<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\StudentInvoice;

/**
 * Decides whether an approved scholarship adjustment may touch the ledger now.
 *
 * Keyed on REAL state: active DNG payment requests (which reserve against
 * invoice lines + settlement position, NOT invoice status) and paid amounts.
 * `student_invoices.status` has no `issued` value (FIN-25) — never guard on it
 * existing. Default-deny: any state not explicitly safe routes to review.
 */
class ScholarshipAdjustmentTimingGuard
{
    public const OUTCOME_NO_INVOICE = 'no_invoice';

    public const OUTCOME_APPLY = 'apply';

    public const OUTCOME_REVIEW = 'review';

    /**
     * Why a review outcome was reached. The guard is the only place that knows
     * WHICH state blocked the change, so it names it rather than leaving every
     * caller to render one catch-all sentence.
     */
    public const REASON_INVOICE_CANCELLED = 'invoice_cancelled';

    public const REASON_INVOICE_PAID = 'invoice_paid';

    public const REASON_BLOCKING_DNG = 'blocking_dng';

    public const REASON_INVOICE_STATUS_UNSAFE = 'invoice_status_unsafe';

    /**
     * DNG states that must block a scholarship rewrite: everything still
     * holding collection (model-owned list) PLUS states where DNG already
     * captured money that has not been posted to the invoice yet (paid_*
     * before reconciliation, cancel push in flight) — cached_paid_amount is
     * still 0 there, so the invoice checks alone would say "safe".
     */
    private const BLOCKING_DNG_STATUSES = [
        ...DngPaymentRequest::HOLDING_COLLECTION_STATUSES,
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG,
    ];

    /**
     * @return array{outcome: string, invoice: StudentInvoice|null, reason: string|null}
     */
    public function evaluate(int $studentId, int $semesterId): array
    {
        // The tuition invoice for the target semester = the latest invoice
        // carrying an active tuition_term line.
        $invoice = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereHas('invoiceLines', function ($query): void {
                $query->where('status', 'active')
                    ->whereHas('charge', fn ($charge) => $charge
                        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                        ->where('status', FinanceCharge::STATUS_ACTIVE));
            })
            ->latest('id')
            ->first();

        if ($invoice === null) {
            return ['outcome' => self::OUTCOME_NO_INVOICE, 'invoice' => null, 'reason' => null];
        }

        if ($invoice->status === 'cancelled') {
            return ['outcome' => self::OUTCOME_REVIEW, 'invoice' => $invoice, 'reason' => self::REASON_INVOICE_CANCELLED];
        }

        if ((float) $invoice->cached_paid_amount > 0
            || in_array($invoice->status, ['paid', 'partial', 'overdue'], true)) {
            return ['outcome' => self::OUTCOME_REVIEW, 'invoice' => $invoice, 'reason' => self::REASON_INVOICE_PAID];
        }

        $chargeIds = $invoice->invoiceLines()
            ->where('status', 'active')
            ->pluck('charge_id')
            ->filter()
            ->unique()
            ->all();

        $hasBlockingDng = DngPaymentRequest::query()
            ->whereIn('status', self::BLOCKING_DNG_STATUSES)
            ->whereHas('chargeLinks', fn ($query) => $query->whereIn('finance_charge_id', $chargeIds))
            ->exists();

        if ($hasBlockingDng) {
            return ['outcome' => self::OUTCOME_REVIEW, 'invoice' => $invoice, 'reason' => self::REASON_BLOCKING_DNG];
        }

        // Explicit safe list — anything else is review (default-deny).
        if (! in_array($invoice->status, ['draft', 'pending'], true)) {
            return ['outcome' => self::OUTCOME_REVIEW, 'invoice' => $invoice, 'reason' => self::REASON_INVOICE_STATUS_UNSAFE];
        }

        return ['outcome' => self::OUTCOME_APPLY, 'invoice' => $invoice, 'reason' => null];
    }
}
