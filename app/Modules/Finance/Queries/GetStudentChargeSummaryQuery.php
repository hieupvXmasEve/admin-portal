<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;

/**
 * Student charges API summary totals (total_charges / total_credits / net_amount).
 *
 * ADR-0030: total_credits and net_amount read discount allocations + credit
 * applications (and the legacy negative-line settlement backstop), not only
 * active negative finance_charges rows.
 */
class GetStudentChargeSummaryQuery
{
    public function __construct(
        protected SettlementService $settlementService,
    ) {}

    /**
     * @return array{total_charges: float, total_credits: float, net_amount: float}
     */
    public function handle(int $studentId, ?int $semesterId = null): array
    {
        $totalCharges = $this->totalCharges($studentId, $semesterId);
        $totalCredits = $this->totalCredits($studentId, $semesterId);

        return [
            'total_charges' => $totalCharges,
            'total_credits' => $totalCredits,
            'net_amount' => $totalCharges - $totalCredits,
        ];
    }

    public function totalCharges(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0);

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        return (float) $query->sum('amount');
    }

    public function totalCredits(int $studentId, ?int $semesterId = null): float
    {
        $invoiceQuery = StudentInvoice::query()
            ->where('student_id', $studentId);

        if ($semesterId !== null) {
            $invoiceQuery->where('semester_id', $semesterId);
        }

        $invoices = $invoiceQuery
            ->with([
                'invoiceLines.charge',
                'invoiceLines.discountAllocations.invoiceDiscount',
                'invoiceLines.creditApplications',
            ])
            ->get();

        $ledgerCredits = (float) $invoices->sum(
            fn (StudentInvoice $invoice): float => $this->invoiceReductionTotal($invoice)
        );

        // Uninvoiced legacy negatives (no invoice line) still reduce student totals.
        // Invoiced negatives are folded in via SettlementService's ADR-0030 backstop
        // when no discount/credit carriers exist on the invoice.
        $uninvoicedLegacyCredits = $this->uninvoicedLegacyNegativeCredits($studentId, $semesterId);

        return $ledgerCredits + $uninvoicedLegacyCredits;
    }

    public function netAmount(int $studentId, ?int $semesterId = null): float
    {
        return $this->totalCharges($studentId, $semesterId) - $this->totalCredits($studentId, $semesterId);
    }

    /**
     * Carrier reductions for one invoice: discount allocations + credit applications.
     *
     * Uses SettlementService line helpers so reversed discounts and signed credit
     * applications match settlement truth, but does NOT apply the payment residual
     * clamp from deriveInvoiceSnapshot (charges summary is "giảm trừ", not remaining).
     * Legacy negative lines only fill in when both carriers are empty (ADR-0030).
     */
    private function invoiceReductionTotal(StudentInvoice $invoice): float
    {
        $activeLines = $invoice->invoiceLines
            ->filter(function (InvoiceLine $line): bool {
                if (($line->status ?? 'active') !== 'active') {
                    return false;
                }

                return $line->charge === null || $line->charge->status === FinanceCharge::STATUS_ACTIVE;
            })
            ->values();

        $discount = (float) $activeLines->sum(
            fn (InvoiceLine $line): float => $this->settlementService->getLineDiscountAmount($line)
        );

        $credit = (float) $activeLines->sum(
            fn (InvoiceLine $line): float => $this->settlementService->getLineCreditAmount($line)
        );

        if ($discount == 0.0 && $credit == 0.0) {
            $discount = abs((float) $activeLines
                ->filter(fn (InvoiceLine $line): bool => (float) $line->amount_snapshot < 0)
                ->sum('amount_snapshot'));
        }

        return $discount + $credit;
    }

    /**
     * Active negative charges that have no active invoice line (pure legacy rows
     * not yet attached to settlement). Avoids double-counting negatives already
     * reflected through the invoice reduction path.
     */
    private function uninvoicedLegacyNegativeCredits(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '<', 0)
            ->whereDoesntHave('invoiceLines', fn ($lineQuery) => $lineQuery->where('status', 'active'));

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        return abs((float) $query->sum('amount'));
    }
}
