<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Support\SettlementPosition\Money;

/**
 * Canonical "cash collected but not yet applied to an invoice" formula.
 * Extracted from ListCollectionProgressQuery so it can be shared with the
 * Revenue report without a second, drifting copy (plan.md decision #1 —
 * only these two call sites move; ~8 other unapplied-cash sites stay as-is).
 */
final class UnappliedCashReader
{
    /**
     * Per-student unapplied cash, same formula ListCollectionProgressQuery
     * always used.
     *
     * @param  list<int>  $studentIds
     * @return array<int, float>
     */
    public function unappliedByStudent(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $payments = Payment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get(['id', 'student_id', 'amount']);

        if ($payments->isEmpty()) {
            return [];
        }

        $appliedByPayment = PaymentApplication::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->selectRaw('payment_id, SUM(amount) as applied')
            ->groupBy('payment_id')
            ->pluck('applied', 'payment_id');
        $disposedByPayment = PaymentSurplusDisposition::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->whereIn('type', [PaymentSurplusDisposition::TYPE_REFUND, PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT])
            ->selectRaw('payment_id, SUM(amount) as amount')
            ->groupBy('payment_id')
            ->pluck('amount', 'payment_id');

        $unapplied = [];
        foreach ($payments as $payment) {
            $applied = (float) ($appliedByPayment[$payment->id] ?? 0.0);
            $disposed = (float) ($disposedByPayment[$payment->id] ?? 0.0);
            $remaining = max(0.0, (float) $payment->amount - $applied - $disposed);
            $unapplied[(int) $payment->student_id] = ($unapplied[(int) $payment->student_id] ?? 0.0) + $remaining;
        }

        return $unapplied;
    }

    /**
     * School-wide totals for the Revenue report's "unattributed" band: cash
     * collected but not resolved to any semester, plus refund/forfeit
     * dispositions. Always school-wide and unfiltered — this money is by
     * definition outside any semester/campus scope (plan.md "Bẫy phải tránh").
     *
     * @return array{unapplied: Money, refund: Money, retain_forfeit: Money}
     */
    public function globalTotals(): array
    {
        $payments = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->get(['id', 'amount']);

        $refund = Money::zero();
        $retainForfeit = Money::zero();
        $unapplied = Money::zero();

        if ($payments->isEmpty()) {
            return ['unapplied' => $unapplied, 'refund' => $refund, 'retain_forfeit' => $retainForfeit];
        }

        $paymentIds = $payments->pluck('id');
        $appliedByPayment = PaymentApplication::query()
            ->whereIn('payment_id', $paymentIds)
            ->selectRaw('payment_id, SUM(amount) as applied')
            ->groupBy('payment_id')
            ->pluck('applied', 'payment_id');

        $refundByPayment = [];
        $retainForfeitByPayment = [];
        $dispositions = PaymentSurplusDisposition::query()
            ->whereIn('payment_id', $paymentIds)
            ->whereIn('type', [PaymentSurplusDisposition::TYPE_REFUND, PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT])
            ->selectRaw('payment_id, type, SUM(amount) as amount')
            ->groupBy('payment_id', 'type')
            ->get();

        foreach ($dispositions as $row) {
            if ($row->type === PaymentSurplusDisposition::TYPE_REFUND) {
                $refundByPayment[(int) $row->payment_id] = (string) $row->amount;
            } else {
                $retainForfeitByPayment[(int) $row->payment_id] = (string) $row->amount;
            }
        }

        foreach ($payments as $payment) {
            $paymentAmount = Money::vnd((string) $payment->amount);
            $applied = Money::vnd((string) ($appliedByPayment[$payment->id] ?? '0'));
            $paymentRefund = Money::vnd($refundByPayment[(int) $payment->id] ?? '0');
            $paymentRetainForfeit = Money::vnd($retainForfeitByPayment[(int) $payment->id] ?? '0');

            $refund = $refund->add($paymentRefund);
            $retainForfeit = $retainForfeit->add($paymentRetainForfeit);

            $remaining = $paymentAmount->subtract($applied)->subtract($paymentRefund)->subtract($paymentRetainForfeit);
            if ($remaining->isPositive()) {
                $unapplied = $unapplied->add($remaining);
            }
        }

        return ['unapplied' => $unapplied, 'refund' => $refund, 'retain_forfeit' => $retainForfeit];
    }
}
