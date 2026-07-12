<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Collection;

final class SettlementPositionWorklistPresenter
{
    /**
     * @return array{
     *     valid: bool,
     *     settlement_state: string,
     *     settlement_label: string,
     *     gross: ?float,
     *     discount: ?float,
     *     cash: ?float,
     *     credit: ?float,
     *     net: ?float,
     *     remaining: ?float,
     *     issues: list<array{code:string,severity:string,blocking:bool,evidence:array<string,int|string>,finance_invariant_code:?string}>,
     * }
     */
    public function summarize(SettlementPosition $position, bool $hasSurplus = false): array
    {
        if (! $position->isValid() || $position->amounts === null) {
            return [
                'valid' => false,
                'settlement_state' => $position->settlement_state,
                'settlement_label' => 'Cần kiểm tra',
                'gross' => null,
                'discount' => null,
                'cash' => null,
                'credit' => null,
                'net' => null,
                'remaining' => null,
                'issues' => array_map(
                    static fn (SettlementPositionIssue $issue): array => [
                        'code' => $issue->code,
                        'severity' => $issue->severity,
                        'blocking' => $issue->blocking,
                        'evidence' => $issue->evidence,
                        'finance_invariant_code' => $issue->finance_invariant_code,
                    ],
                    $position->issues,
                ),
            ];
        }

        $amounts = $position->amounts;
        $remaining = (float) $amounts->remaining->amount;
        $cash = (float) $amounts->cash->amount;

        return [
            'valid' => true,
            'settlement_state' => $position->settlement_state,
            'settlement_label' => $remaining > 0
                ? 'Còn phải thu'
                : ($hasSurplus ? 'Còn dư' : ($cash > 0 ? 'Đã thu' : 'Đã giảm trừ')),
            'gross' => (float) $amounts->gross->amount,
            'discount' => (float) $amounts->discount->amount,
            'cash' => $cash,
            'credit' => (float) $amounts->credit->amount,
            'net' => (float) $amounts->gross->subtract($amounts->discount)->amount,
            'remaining' => $remaining,
            'issues' => [],
        ];
    }

    /** @param Collection<int, Payment> $payments */
    public function unappliedCash(Collection $payments): float
    {
        $totalPayments = (float) $payments->sum('amount');
        $allocatedAmount = (float) $payments->sum(
            fn (Payment $payment): float => (float) $payment->applications->sum('amount'),
        );

        return max(0, $totalPayments - $allocatedAmount);
    }

    public function netAmountToCollect(?float $remaining, float $unappliedCash): ?float
    {
        return $remaining === null ? null : max(0, $remaining - $unappliedCash);
    }

    public function capCollectionAmount(?float $remaining, ?float $candidate): float
    {
        if ($remaining === null || $remaining <= 0) {
            return 0.0;
        }

        return min($remaining, $candidate !== null && $candidate > 0 ? $candidate : $remaining);
    }
}
