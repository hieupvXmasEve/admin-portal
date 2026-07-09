<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;

class GetStudentBalanceQuery
{
    public function __construct(
        protected SettlementService $settlementService,
    ) {}

    /**
     * Get student balance summary.
     */
    public function handle(int $studentId, ?int $semesterId = null): array
    {
        $invoiceQuery = StudentInvoice::query()
            ->where('student_id', $studentId);

        if ($semesterId) {
            $invoiceQuery->where('semester_id', $semesterId);
        }

        $invoices = $invoiceQuery
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->get();

        $snapshots = $invoices->map(fn (StudentInvoice $invoice) => $this->settlementService->deriveInvoiceSnapshot($invoice));

        $totalCharges = (float) $snapshots->sum('gross');
        $totalCredits = (float) $snapshots->sum('discount');
        $netCharges = (float) $snapshots->sum('net');
        $totalPaid = (float) $snapshots->sum('paid');

        // Get unapplied credit from payments
        $unappliedCredit = $this->getUnappliedCredits($studentId);

        $balance = $netCharges - $totalPaid;

        return [
            'total_charges' => $totalCharges,
            'total_credits' => $totalCredits,
            'net_charges' => $netCharges,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'unapplied_credit' => $unappliedCredit,
            'status' => $this->determineBalanceStatus($balance),
        ];
    }

    /**
     * Get unapplied credits (payment amounts not yet allocated).
     */
    protected function getUnappliedCredits(int $studentId): float
    {
        $payments = Payment::where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get();

        return $payments->sum(function ($payment) {
            return max(0, $payment->unapplied_amount);
        });
    }

    /**
     * Determine balance status.
     */
    protected function determineBalanceStatus(float $balance): string
    {
        if ($balance === 0.0) {
            return 'paid';
        } elseif ($balance > 0) {
            return 'outstanding';
        } else {
            return 'overpaid';
        }
    }
}
