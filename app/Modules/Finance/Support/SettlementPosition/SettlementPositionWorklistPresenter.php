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
     *     money_item_status: array{code: string, label_staff: string, label_student: string, hide_amounts: bool},
     *     gross: ?float,
     *     discount: ?float,
     *     cash: ?float,
     *     credit: ?float,
     *     net: ?float,
     *     remaining: ?float,
     *     issues: list<array{code:string,severity:string,blocking:bool,evidence:array<string,int|string>,finance_invariant_code:?string}>,
     * }
     */
    public function summarize(SettlementPosition $position, bool $hasSurplus = false, ?MoneyItemStatusContext $status = null): array
    {
        $status ??= new MoneyItemStatusContext($position, $hasSurplus);
        $moneyItemStatus = $this->moneyItemStatus($status);

        if (! $position->isValid() || $position->amounts === null) {
            return [
                'valid' => false,
                'settlement_state' => $position->settlement_state,
                'settlement_label' => 'Cần kiểm tra',
                'money_item_status' => $moneyItemStatus,
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
            'money_item_status' => $moneyItemStatus,
            'gross' => (float) $amounts->gross->amount,
            'discount' => (float) $amounts->discount->amount,
            'cash' => $cash,
            'credit' => (float) $amounts->credit->amount,
            'net' => (float) $amounts->netDue()->amount,
            'remaining' => $remaining,
            'issues' => [],
        ];
    }

    /**
     * @return array{code: string, label_staff: string, label_student: string, hide_amounts: bool}
     */
    public function moneyItemStatus(MoneyItemStatusContext $context): array
    {
        $position = $context->position;
        $remaining = $position->isValid() && $position->amounts !== null
            ? (float) $position->amounts->remaining->amount
            : null;

        $code = match (true) {
            ! $position->isValid()
                || $position->amounts === null
                || in_array($position->settlement_state, [SettlementPosition::STATE_MISSING, SettlementPosition::STATE_INVALID], true)
                || $context->dngNeedsReview => MoneyItemStatusContext::REVIEWING,
            $context->obligationCancelled || $context->chargeVoid => MoneyItemStatusContext::ADJUSTED,
            $remaining !== null && $remaining <= 0 => MoneyItemStatusContext::COMPLETED,
            $context->dngHoldingThisItem => MoneyItemStatusContext::PROCESSING,
            $remaining !== null && $remaining > 0 && $context->dueDate !== null && $context->dueDate->getTimestamp() < time() => MoneyItemStatusContext::OVERDUE,
            default => MoneyItemStatusContext::AWAITING_PAYMENT,
        };

        return [
            'code' => $code,
            'label_staff' => $this->staffLabel($code),
            'label_student' => $this->studentLabel($code),
            'hide_amounts' => $code === MoneyItemStatusContext::REVIEWING,
        ];
    }

    private function staffLabel(string $code): string
    {
        return match ($code) {
            MoneyItemStatusContext::REVIEWING => 'Đang rà soát',
            MoneyItemStatusContext::ADJUSTED => 'Đã điều chỉnh',
            MoneyItemStatusContext::COMPLETED => 'Đã hoàn tất',
            MoneyItemStatusContext::PROCESSING => 'Đang xử lý thanh toán',
            MoneyItemStatusContext::OVERDUE => 'Quá hạn',
            default => 'Chờ thanh toán',
        };
    }

    private function studentLabel(string $code): string
    {
        return match ($code) {
            MoneyItemStatusContext::REVIEWING => 'Khoản này đang được nhà trường kiểm tra. Vui lòng quay lại sau.',
            MoneyItemStatusContext::ADJUSTED => 'Khoản này đã được điều chỉnh.',
            MoneyItemStatusContext::COMPLETED => 'Khoản này đã hoàn tất.',
            MoneyItemStatusContext::PROCESSING => 'Thanh toán của bạn đang được xử lý.',
            MoneyItemStatusContext::OVERDUE => 'Khoản này đã quá hạn nộp.',
            default => 'Khoản này đang chờ thanh toán.',
        };
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
