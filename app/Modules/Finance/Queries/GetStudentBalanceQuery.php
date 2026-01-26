<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class GetStudentBalanceQuery
{
    /**
     * Get student balance summary.
     */
    public function handle(int $studentId, ?int $semesterId = null): array
    {
        $chargeQuery = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE);

        if ($semesterId) {
            $chargeQuery->where('semester_id', $semesterId);
        }

        $totalCharges = (float) (clone $chargeQuery)->where('amount', '>', 0)->sum('amount');
        $totalCredits = abs((float) (clone $chargeQuery)->where('amount', '<', 0)->sum('amount'));
        $netCharges = $totalCharges - $totalCredits;

        // Get total paid
        $chargeIds = (clone $chargeQuery)->pluck('id');
        $totalPaid = (float) PaymentAllocation::whereIn('charge_id', $chargeIds)->sum('allocated_amount');

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
            ->with('allocations')
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
