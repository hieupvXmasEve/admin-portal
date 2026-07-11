<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Shared\Contracts\Finance\SettlementPositionReader;

final class CurrentPayableSettlementPositionReader implements SettlementPositionReader
{
    public function forPayableLine(int $payableLineId): SettlementPosition
    {
        $line = InvoiceLine::query()
            ->with('charge.financeObligation')
            ->find($payableLineId);

        if (! $line instanceof InvoiceLine) {
            return $this->missing(
                SettlementPosition::SCOPE_PAYABLE_LINE,
                $payableLineId,
                SettlementPositionIssue::MISSING_PAYABLE_LINE,
            );
        }

        return $this->readLine(
            line: $line,
            scopeType: SettlementPosition::SCOPE_PAYABLE_LINE,
            scopeId: $payableLineId,
        );
    }

    public function forFinanceObligation(int $financeObligationId): SettlementPosition
    {
        $obligation = FinanceObligation::query()->find($financeObligationId);
        if (! $obligation instanceof FinanceObligation) {
            return $this->missing(
                SettlementPosition::SCOPE_FINANCE_OBLIGATION,
                $financeObligationId,
                SettlementPositionIssue::MISSING_FINANCE_OBLIGATION,
            );
        }

        $lines = InvoiceLine::query()
            ->with('charge.financeObligation')
            ->where('status', 'active')
            ->whereHas('charge', fn ($query) => $query->where('finance_obligation_id', $obligation->id))
            ->limit(2)
            ->get();

        if ($lines->isEmpty()) {
            return $this->missing(
                SettlementPosition::SCOPE_FINANCE_OBLIGATION,
                $financeObligationId,
                SettlementPositionIssue::MISSING_PAYABLE_LINE,
                $financeObligationId,
            );
        }

        if ($lines->count() > 1) {
            return SettlementPosition::invalid(
                scopeType: SettlementPosition::SCOPE_FINANCE_OBLIGATION,
                scopeId: $financeObligationId,
                payableLineId: null,
                financeObligationId: $financeObligationId,
                rawEvidence: SettlementPositionRawEvidence::empty(),
                issues: [SettlementPositionIssue::blocking(
                    SettlementPositionIssue::DUPLICATE_ACTIVE_PAYABLE_LINES,
                    ['finance_obligation_id' => $financeObligationId],
                    'INV-3',
                )],
            );
        }

        return $this->readLine(
            line: $lines->firstOrFail(),
            scopeType: SettlementPosition::SCOPE_FINANCE_OBLIGATION,
            scopeId: $financeObligationId,
        );
    }

    private function readLine(InvoiceLine $line, string $scopeType, int $scopeId): SettlementPosition
    {
        $charge = $line->charge;
        $financeObligationId = $charge?->finance_obligation_id === null
            ? null
            : (int) $charge->finance_obligation_id;

        $gross = Money::vnd((string) $line->amount_snapshot);
        $cash = Money::vnd($this->sumCompletedCash((int) $line->id));
        [$discount, $reversedDiscountResidue] = $this->discountEvidence((int) $line->id);
        [$credit, $inactiveCreditResidue, $creditCurrencies] = $this->creditEvidence((int) $line->id);
        $remaining = $gross->subtract($discount)->subtract($cash)->subtract($credit);
        $rawEvidence = new SettlementPositionRawEvidence(
            gross: $gross,
            discount: $discount,
            cash: $cash,
            credit: $credit,
            remaining: $remaining,
            reversed_discount_residue: $reversedDiscountResidue,
            inactive_credit_residue: $inactiveCreditResidue,
        );

        $issues = [];
        if ($line->status !== 'active') {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::PAYABLE_LINE_NOT_ACTIVE, [
                'payable_line_id' => (int) $line->id,
                'status' => (string) $line->status,
            ]);
        }

        if (! $charge instanceof FinanceCharge || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::FINANCE_CHARGE_NOT_ACTIVE,
                ['payable_line_id' => (int) $line->id],
                'INV-4',
            );
        }

        if (! $gross->isPositive()) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::PAYABLE_LINE_NOT_COLLECTIBLE, [
                'payable_line_id' => (int) $line->id,
                'gross' => $gross->amount,
            ]);
        }

        $obligationCurrency = $charge?->financeObligation?->currency;
        if ($obligationCurrency === null) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::MISSING_CURRENCY, [
                'payable_line_id' => (int) $line->id,
            ]);
        } elseif ($obligationCurrency !== Money::VND) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::UNSUPPORTED_CURRENCY, [
                'currency' => (string) $obligationCurrency,
            ]);
        }

        foreach ($creditCurrencies as $currency) {
            if ($currency !== Money::VND) {
                $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::UNSUPPORTED_CURRENCY, [
                    'currency' => $currency,
                ]);
            }
        }

        if ($cash->isNegative()) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::NEGATIVE_CASH_APPLICATION, [
                'cash' => $cash->amount,
            ]);
        }

        if ($discount->isNegative()) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::NEGATIVE_DISCOUNT_ALLOCATION, [
                'discount' => $discount->amount,
            ]);
        }

        if ($credit->isNegative()) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::NEGATIVE_CREDIT_APPLICATION, [
                'credit' => $credit->amount,
            ]);
        }

        if (! $reversedDiscountResidue->isZero()) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::REVERSED_DISCOUNT_RESIDUE,
                ['reversed_discount_residue' => $reversedDiscountResidue->amount],
                'INV-5',
            );
        }

        if (! $inactiveCreditResidue->isZero()) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::INACTIVE_CREDIT_RESIDUE, [
                'inactive_credit_residue' => $inactiveCreditResidue->amount,
            ]);
        }

        $netDueAfterDiscount = $gross->subtract($discount);
        $remainingAfterCash = $netDueAfterDiscount->subtract($cash);

        if ($discount->isGreaterThan($gross)) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::DISCOUNT_EXCEEDS_GROSS,
                ['gross' => $gross->amount, 'discount' => $discount->amount],
                'INV-8',
            );
        }

        if ($cash->isPositive() && $cash->isGreaterThan($netDueAfterDiscount)) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::CASH_EXCEEDS_NET_DUE,
                ['cash' => $cash->amount, 'net_due_after_discount' => $netDueAfterDiscount->amount],
                'INV-8',
            );
        }

        if ($credit->isPositive() && $credit->isGreaterThan($remainingAfterCash)) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::CREDIT_EXCEEDS_REMAINING,
                ['credit' => $credit->amount, 'remaining_after_cash' => $remainingAfterCash->amount],
                'INV-8',
            );
        }

        if ($remaining->isNegative()) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::NEGATIVE_RAW_REMAINING,
                ['remaining' => $remaining->amount],
                'INV-8',
            );
        }

        if ($issues !== []) {
            return SettlementPosition::invalid(
                scopeType: $scopeType,
                scopeId: $scopeId,
                payableLineId: (int) $line->id,
                financeObligationId: $financeObligationId,
                rawEvidence: $rawEvidence,
                issues: $issues,
            );
        }

        $amounts = new SettlementPositionAmounts(
            gross: $gross,
            discount: $discount,
            cash: $cash,
            credit: $credit,
            remaining: $remaining,
        );

        return new SettlementPosition(
            scope_type: $scopeType,
            scope_id: $scopeId,
            payable_line_id: (int) $line->id,
            finance_obligation_id: $financeObligationId,
            position_mode: SettlementPosition::MODE_CURRENT,
            captured_at: now()->toImmutable(),
            settlement_state: $this->settlementState($amounts),
            valid: true,
            raw_evidence: $rawEvidence,
            amounts: $amounts,
            issues: [],
        );
    }

    private function missing(
        string $scopeType,
        int $scopeId,
        string $issueCode,
        ?int $financeObligationId = null,
    ): SettlementPosition {
        return new SettlementPosition(
            scope_type: $scopeType,
            scope_id: $scopeId,
            payable_line_id: null,
            finance_obligation_id: $financeObligationId,
            position_mode: SettlementPosition::MODE_CURRENT,
            captured_at: now()->toImmutable(),
            settlement_state: SettlementPosition::STATE_MISSING,
            valid: false,
            raw_evidence: SettlementPositionRawEvidence::empty(),
            amounts: null,
            issues: [SettlementPositionIssue::blocking($issueCode, ['scope_id' => $scopeId])],
        );
    }

    private function sumCompletedCash(int $payableLineId): string
    {
        return (string) PaymentApplication::query()
            ->join('payments', 'payments.id', '=', 'payment_applications.payment_id')
            ->where('payment_applications.invoice_line_id', $payableLineId)
            ->where('payments.status', Payment::STATUS_COMPLETED)
            ->sum('payment_applications.amount');
    }

    /**
     * @return array{0: Money, 1: Money}
     */
    private function discountEvidence(int $payableLineId): array
    {
        $row = DiscountAllocation::query()
            ->join('invoice_discounts', 'invoice_discounts.id', '=', 'discount_allocations.invoice_discount_id')
            ->where('discount_allocations.invoice_line_id', $payableLineId)
            ->selectRaw('COALESCE(SUM(discount_allocations.amount), 0) as amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoice_discounts.status = "reversed" THEN discount_allocations.amount ELSE 0 END), 0) as reversed_residue')
            ->first();

        return [
            Money::vnd((string) ($row->amount ?? 0)),
            Money::vnd((string) ($row->reversed_residue ?? 0)),
        ];
    }

    /**
     * @return array{0: Money, 1: Money, 2: list<string>}
     */
    private function creditEvidence(int $payableLineId): array
    {
        $row = CreditApplication::query()
            ->join('finance_credit_entitlements', 'finance_credit_entitlements.id', '=', 'credit_applications.finance_credit_entitlement_id')
            ->where('credit_applications.invoice_line_id', $payableLineId)
            ->selectRaw('COALESCE(SUM(credit_applications.amount), 0) as amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN finance_credit_entitlements.lifecycle_status != "approved" THEN credit_applications.amount ELSE 0 END), 0) as inactive_residue')
            ->selectRaw('GROUP_CONCAT(DISTINCT finance_credit_entitlements.currency) as currencies')
            ->first();

        $currencies = $row?->currencies === null || $row->currencies === ''
            ? []
            : array_values(array_filter(explode(',', (string) $row->currencies)));

        return [
            Money::vnd((string) ($row->amount ?? 0)),
            Money::vnd((string) ($row->inactive_residue ?? 0)),
            $currencies,
        ];
    }

    private function settlementState(SettlementPositionAmounts $amounts): string
    {
        if ($amounts->remaining->isPositive()) {
            return $amounts->cash->isZero() && $amounts->discount->isZero() && $amounts->credit->isZero()
                ? SettlementPosition::STATE_UNPAID
                : SettlementPosition::STATE_PARTIALLY_SETTLED;
        }

        return $amounts->cash->isPositive()
            ? SettlementPosition::STATE_SETTLED_BY_CASH
            : SettlementPosition::STATE_SETTLED_BY_REDUCTION;
    }
}
