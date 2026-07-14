<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionRawEvidence;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;

final class StudentFinanceSettlementPositionReader
{
    public const STUDENT_UNAVAILABLE_MESSAGE = 'Thông tin thanh toán hiện chưa khả dụng.';

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
    ) {}

    /**
     * Read the canonical current position for a student's Billing Account.
     * A semester narrows the exact payable-line set before Finance aggregates it.
     *
     * @return array{
     *     valid: bool,
     *     scope_type: string,
     *     scope_id: int,
     *     billing_account_id: int|null,
     *     position_mode: string,
     *     captured_at: string,
     *     snapshot_version: string,
     *     settlement_state: string,
     *     gross: float|null,
     *     discount: float|null,
     *     net_due: float|null,
     *     cash_applied: float|null,
     *     credit_applied: float|null,
     *     remaining_collectible: float|null,
     *     unapplied_cash: float|null,
     *     total_cash_received: float|null,
     *     status: string,
     *     money_actions_available: bool,
     *     student_message: string|null,
     *     issues: list<array{code: string, blocking: bool, evidence: array<string, int|string>}>
     * }
     */
    public function current(int $studentId, ?int $semesterId = null): array
    {
        $billingAccountId = BillingAccount::query()
            ->where('student_id', $studentId)
            ->value('id');

        if ($billingAccountId === null) {
            return $this->invalidSummary(
                SettlementPosition::SCOPE_BILLING_ACCOUNT,
                0,
                null,
                [SettlementPositionIssue::blocking(
                    SettlementPositionIssue::MISSING_BILLING_ACCOUNT,
                    ['student_id' => $studentId],
                )],
            );
        }

        $position = $semesterId === null
            ? $this->settlementPositionReader->forBillingAccount((int) $billingAccountId)
            : $this->positionForSemester($studentId, $semesterId);

        if ($position->isValid()
            && $position->amounts?->gross->isZero()
            && $this->hasUnmaterializedPayableLines($studentId, $semesterId)) {
            $position = SettlementPosition::invalid(
                scopeType: $position->scope_type,
                scopeId: $position->scope_id,
                payableLineId: null,
                financeObligationId: null,
                rawEvidence: $position->raw_evidence,
                issues: [SettlementPositionIssue::blocking(
                    SettlementPositionIssue::MISSING_FINANCE_OBLIGATION,
                    ['student_id' => $studentId],
                )],
                billingAccountId: (int) $billingAccountId,
            );
        }

        return $this->serialize($position, (int) $billingAccountId, $this->unappliedCash($studentId));
    }

    private function positionForSemester(int $studentId, int $semesterId): SettlementPosition
    {
        $lineIds = InvoiceLine::query()
            ->whereHas('invoice', fn ($query) => $query
                ->where('student_id', $studentId)
                ->where('semester_id', $semesterId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return $this->settlementPositionReader->batch([
            SettlementPositionScope::payableLines($lineIds),
        ])[0];
    }

    private function hasUnmaterializedPayableLines(int $studentId, ?int $semesterId): bool
    {
        return InvoiceLine::query()
            ->where('invoice_lines.status', 'active')
            ->where('invoice_lines.amount_snapshot', '>', 0)
            ->whereHas('charge', fn ($query) => $query->where('status', 'active'))
            ->whereHas('invoice', fn ($query) => $query
                ->where('student_id', $studentId)
                ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId)))
            ->exists();
    }

    /** @return array{valid: bool, remaining_collectible: float|null, money_actions_available: bool} */
    private function serialize(SettlementPosition $position, int $billingAccountId, float $unappliedCash): array
    {
        $amounts = $position->amounts;
        $valid = $position->isValid() && $amounts !== null;

        return [
            'valid' => $valid,
            'scope_type' => $position->scope_type,
            'scope_id' => $position->scope_id,
            'billing_account_id' => $billingAccountId,
            'position_mode' => $position->position_mode,
            'captured_at' => $position->captured_at->toIso8601String(),
            'snapshot_version' => $position->snapshot_version,
            'settlement_state' => $position->settlement_state,
            'gross' => $valid ? (float) $amounts->gross->amount : null,
            'discount' => $valid ? (float) $amounts->discount->amount : null,
            'net_due' => $valid ? (float) $amounts->netDue()->amount : null,
            'cash_applied' => $valid ? (float) $amounts->cash->amount : null,
            'credit_applied' => $valid ? (float) $amounts->credit->amount : null,
            'remaining_collectible' => $valid ? (float) $amounts->remaining->amount : null,
            'unapplied_cash' => $valid ? $unappliedCash : null,
            'total_cash_received' => $valid
                ? round((float) $amounts->cash->amount + $unappliedCash, 2)
                : null,
            'status' => $valid ? $this->status($position) : 'unknown',
            'money_actions_available' => $valid,
            'student_message' => $valid ? null : self::STUDENT_UNAVAILABLE_MESSAGE,
            'issues' => array_map(
                static fn (SettlementPositionIssue $issue): array => [
                    'code' => $issue->code,
                    'blocking' => $issue->blocking,
                    'evidence' => $issue->evidence,
                ],
                $position->issues,
            ),
        ];
    }

    /** @param list<SettlementPositionIssue> $issues */
    private function invalidSummary(string $scopeType, int $scopeId, ?int $billingAccountId, array $issues): array
    {
        $position = SettlementPosition::invalid(
            scopeType: $scopeType,
            scopeId: $scopeId,
            payableLineId: null,
            financeObligationId: null,
            rawEvidence: SettlementPositionRawEvidence::empty(),
            issues: $issues,
            billingAccountId: $billingAccountId,
        );

        return $this->serialize($position, $billingAccountId ?? 0, 0.0);
    }

    private function status(SettlementPosition $position): string
    {
        return match ($position->settlement_state) {
            SettlementPosition::STATE_UNPAID,
            SettlementPosition::STATE_PARTIALLY_SETTLED => 'outstanding',
            SettlementPosition::STATE_SETTLED_BY_CASH,
            SettlementPosition::STATE_SETTLED_BY_REDUCTION => 'paid',
            default => 'unknown',
        };
    }

    private function unappliedCash(int $studentId): float
    {
        $payments = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->with('applications')
            ->get();

        if ($payments->isEmpty()) {
            return 0.0;
        }

        $disposedByPayment = PaymentSurplusDisposition::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->whereIn('type', [
                PaymentSurplusDisposition::TYPE_REFUND,
                PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT,
            ])
            ->selectRaw('payment_id, SUM(amount) as amount')
            ->groupBy('payment_id')
            ->pluck('amount', 'payment_id');

        return round((float) $payments->sum(function (Payment $payment) use ($disposedByPayment): float {
            $applied = (float) $payment->applications->sum('amount');
            $disposed = (float) ($disposedByPayment->get($payment->id) ?? 0);

            return max(0, (float) $payment->amount - $applied - $disposed);
        }), 2);
    }
}
