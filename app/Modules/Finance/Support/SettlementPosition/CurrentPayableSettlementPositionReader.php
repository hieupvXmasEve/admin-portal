<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CurrentPayableSettlementPositionReader implements SettlementPositionReader
{
    private ?CarbonImmutable $batchCapturedAt = null;

    private ?string $batchSnapshotVersion = null;

    public function forPayableLine(int $payableLineId, ?CarbonImmutable $asOf = null): SettlementPosition
    {
        return $this->batch([SettlementPositionScope::payableLine($payableLineId, $asOf)])[0];
    }

    public function forFinanceObligation(int $financeObligationId, ?CarbonImmutable $asOf = null): SettlementPosition
    {
        return $this->batch([SettlementPositionScope::financeObligation($financeObligationId, $asOf)])[0];
    }

    public function forInvoice(int $invoiceId, ?CarbonImmutable $asOf = null): SettlementPosition
    {
        return $this->batch([SettlementPositionScope::invoice($invoiceId, $asOf)])[0];
    }

    public function forFeeType(
        int $billingAccountId,
        string $feeType,
        ?CarbonImmutable $asOf = null,
    ): SettlementPosition {
        return $this->batch([SettlementPositionScope::feeType($billingAccountId, $feeType, $asOf)])[0];
    }

    public function forBillingAccount(int $billingAccountId, ?CarbonImmutable $asOf = null): SettlementPosition
    {
        return $this->batch([SettlementPositionScope::billingAccount($billingAccountId, $asOf)])[0];
    }

    /**
     * @param  list<int>  $payableLineIds
     */
    public function forPayableLines(array $payableLineIds, ?CarbonImmutable $asOf = null): SettlementPosition
    {
        return $this->batch([SettlementPositionScope::payableLines($payableLineIds, $asOf)])[0];
    }

    /**
     * @param  list<SettlementPositionScope>  $scopes
     * @return list<SettlementPosition>
     */
    public function batch(array $scopes): array
    {
        try {
            return DB::transaction(function () use ($scopes): array {
                $results = [];

                foreach ($this->groupScopesBySnapshot($scopes) as $group) {
                    $this->batchCapturedAt = $group['as_of'] ?? CarbonImmutable::now();
                    $this->batchSnapshotVersion = hash('sha256', implode('|', [
                        $group['as_of'] === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
                        $this->batchCapturedAt->toIso8601String(),
                    ]));
                    $contexts = $this->lineContextsForScopes($group['scopes'], $group['as_of']);

                    foreach ($group['indexes'] as $index) {
                        $scope = $scopes[$index];
                        $results[$index] = $this->positionForScope($scope, $contexts);
                    }
                }

                ksort($results);

                return array_values($results);
            });
        } finally {
            $this->batchCapturedAt = null;
            $this->batchSnapshotVersion = null;
        }
    }

    /**
     * @param  list<SettlementPositionScope>  $scopes
     * @return array<string, array{as_of: ?CarbonImmutable, indexes: list<int>, scopes: list<SettlementPositionScope>}>
     */
    private function groupScopesBySnapshot(array $scopes): array
    {
        $groups = [];

        foreach ($scopes as $index => $scope) {
            $key = $scope->as_of?->toIso8601String() ?? 'current';
            $groups[$key] ??= [
                'as_of' => $scope->as_of,
                'indexes' => [],
                'scopes' => [],
            ];
            $groups[$key]['indexes'][] = $index;
            $groups[$key]['scopes'][] = $scope;
        }

        return $groups;
    }

    /**
     * @param  list<SettlementPositionScope>  $scopes
     * @return array<int, array{line: InvoiceLine, position: SettlementPosition}>
     */
    private function lineContextsForScopes(array $scopes, ?CarbonImmutable $asOf): array
    {
        $directLineIds = [];
        $invoiceIds = [];
        $obligationIds = [];
        $billingAccountIds = [];
        $feeTypeScopes = [];

        foreach ($scopes as $scope) {
            match ($scope->type) {
                SettlementPosition::SCOPE_PAYABLE_LINE => $directLineIds[] = $scope->id,
                SettlementPosition::SCOPE_PAYABLE_LINES => array_push($directLineIds, ...$scope->payable_line_ids),
                SettlementPosition::SCOPE_INVOICE => $invoiceIds[] = $scope->id,
                SettlementPosition::SCOPE_FINANCE_OBLIGATION => $obligationIds[] = $scope->id,
                SettlementPosition::SCOPE_BILLING_ACCOUNT => $billingAccountIds[] = $scope->billing_account_id,
                SettlementPosition::SCOPE_FEE_TYPE => $feeTypeScopes[] = $scope,
                default => null,
            };
        }

        $directLineIds = $this->integerIds($directLineIds);
        $invoiceIds = $this->integerIds($invoiceIds);
        $obligationIds = $this->integerIds($obligationIds);
        $billingAccountIds = $this->integerIds($billingAccountIds);

        if ($directLineIds === [] && $invoiceIds === [] && $obligationIds === [] && $billingAccountIds === [] && $feeTypeScopes === []) {
            return [];
        }

        $lines = InvoiceLine::query()
            ->with('charge.financeObligation')
            ->where(function ($query) use (
                $directLineIds,
                $invoiceIds,
                $obligationIds,
                $billingAccountIds,
                $feeTypeScopes,
            ): void {
                if ($directLineIds !== []) {
                    $query->orWhereIn('invoice_lines.id', $directLineIds);
                }

                if ($invoiceIds !== []) {
                    $query->orWhereIn('invoice_lines.invoice_id', $invoiceIds);
                }

                if ($obligationIds !== []) {
                    $query->orWhereHas('charge', fn ($chargeQuery) => $chargeQuery->whereIn(
                        'finance_obligation_id',
                        $obligationIds,
                    ));
                }

                if ($billingAccountIds !== []) {
                    $query->orWhereHas('charge.financeObligation', fn ($obligationQuery) => $obligationQuery->whereIn(
                        'billing_account_id',
                        $billingAccountIds,
                    ));
                }

                foreach ($feeTypeScopes as $scope) {
                    $query->orWhereHas('charge', function ($chargeQuery) use ($scope): void {
                        $chargeQuery
                            ->where('charge_type', $scope->fee_type)
                            ->whereHas('financeObligation', fn ($obligationQuery) => $obligationQuery->where(
                                'billing_account_id',
                                $scope->billing_account_id,
                            ));
                    });
                }
            })
            ->get();

        return $this->buildLineContexts($lines, $asOf);
    }

    /**
     * @return array<int, array{line: InvoiceLine, position: SettlementPosition}>
     */
    private function buildLineContexts(Collection $lines, ?CarbonImmutable $asOf): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $lineIds = $lines->modelKeys();
        $cashEvidence = $this->cashEvidence($lineIds, $asOf);
        $discountEvidence = $this->discountEvidence($lineIds, $asOf);
        $creditEvidence = $this->creditEvidence($lineIds, $asOf);
        $contexts = [];

        foreach ($lines as $line) {
            $lineId = (int) $line->id;
            $contexts[$lineId] = [
                'line' => $line,
                'position' => $this->linePosition(
                    line: $line,
                    cashEvidence: $cashEvidence[$lineId] ?? ['amount' => '0', 'unreliable_status' => 0],
                    discountEvidence: $discountEvidence[$lineId] ?? [
                        'amount' => '0',
                        'reversed_residue' => '0',
                        'untimestamped_evidence' => 0,
                    ],
                    creditEvidence: $creditEvidence[$lineId] ?? [
                        'amount' => '0',
                        'inactive_residue' => '0',
                        'currencies' => '',
                    ],
                    asOf: $asOf,
                ),
            ];
        }

        return $contexts;
    }

    /**
     * @param  array{amount: mixed, unreliable_status: mixed}  $cashEvidence
     * @param  array{amount: mixed, reversed_residue: mixed, untimestamped_evidence: mixed}  $discountEvidence
     * @param  array{amount: mixed, inactive_residue: mixed, currencies: mixed}  $creditEvidence
     */
    private function linePosition(
        InvoiceLine $line,
        array $cashEvidence,
        array $discountEvidence,
        array $creditEvidence,
        ?CarbonImmutable $asOf,
    ): SettlementPosition {
        $charge = $line->charge;
        $obligation = $charge?->financeObligation;
        $gross = Money::vnd((string) $line->amount_snapshot);
        $cash = Money::vnd((string) $cashEvidence['amount']);
        $discount = Money::vnd((string) $discountEvidence['amount']);
        $reversedDiscountResidue = Money::vnd((string) $discountEvidence['reversed_residue']);
        $credit = Money::vnd((string) $creditEvidence['amount']);
        $inactiveCreditResidue = Money::vnd((string) $creditEvidence['inactive_residue']);
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

        $this->validateLineState($line, $charge, $asOf, $issues);

        if (! $gross->isPositive()) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::PAYABLE_LINE_NOT_COLLECTIBLE, [
                'payable_line_id' => (int) $line->id,
                'gross' => $gross->amount,
            ]);
        }

        if ($obligation?->currency === null) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::MISSING_CURRENCY, [
                'payable_line_id' => (int) $line->id,
            ]);
        } elseif ($obligation->currency !== Money::VND) {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::UNSUPPORTED_CURRENCY, [
                'currency' => (string) $obligation->currency,
            ]);
        }

        foreach (array_filter(explode(',', (string) $creditEvidence['currencies'])) as $currency) {
            if ($currency !== Money::VND) {
                $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::UNSUPPORTED_CURRENCY, [
                    'currency' => $currency,
                ]);
            }
        }

        if ($asOf !== null && (int) $cashEvidence['unreliable_status'] === 1) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::AS_OF_UNRELIABLE_PAYMENT_STATUS,
                ['payable_line_id' => (int) $line->id],
            );
        }

        if ($asOf !== null && (int) $discountEvidence['untimestamped_evidence'] === 1) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::AS_OF_UNTIMESTAMPED_DISCOUNT_EVIDENCE,
                ['payable_line_id' => (int) $line->id],
            );
        }

        $this->validateRawEvidence(
            gross: $gross,
            cash: $cash,
            discount: $discount,
            credit: $credit,
            remaining: $remaining,
            reversedDiscountResidue: $reversedDiscountResidue,
            inactiveCreditResidue: $inactiveCreditResidue,
            issues: $issues,
        );

        $capturedAt = $this->capturedAt($asOf);
        $financeObligationId = $charge?->finance_obligation_id === null ? null : (int) $charge->finance_obligation_id;
        $billingAccountId = $obligation?->billing_account_id === null ? null : (int) $obligation->billing_account_id;
        $feeType = $charge?->charge_type;

        if ($issues !== []) {
            return SettlementPosition::invalid(
                scopeType: SettlementPosition::SCOPE_PAYABLE_LINE,
                scopeId: (int) $line->id,
                payableLineId: (int) $line->id,
                financeObligationId: $financeObligationId,
                rawEvidence: $rawEvidence,
                issues: $issues,
                invoiceId: (int) $line->invoice_id,
                billingAccountId: $billingAccountId,
                feeType: $feeType,
                positionMode: $asOf === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
                capturedAt: $capturedAt,
            );
        }

        $amounts = new SettlementPositionAmounts($gross, $discount, $cash, $credit, $remaining);

        return new SettlementPosition(
            scope_type: SettlementPosition::SCOPE_PAYABLE_LINE,
            scope_id: (int) $line->id,
            payable_line_id: (int) $line->id,
            finance_obligation_id: $financeObligationId,
            invoice_id: (int) $line->invoice_id,
            billing_account_id: $billingAccountId,
            fee_type: $feeType,
            position_mode: $asOf === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
            captured_at: $capturedAt,
            snapshot_version: $this->snapshotVersion(
                SettlementPosition::SCOPE_PAYABLE_LINE,
                (int) $line->id,
                $capturedAt,
                $rawEvidence,
            ),
            settlement_state: $this->settlementState($amounts),
            valid: true,
            raw_evidence: $rawEvidence,
            amounts: $amounts,
            issues: [],
        );
    }

    /**
     * @param  array<int, array{line: InvoiceLine, position: SettlementPosition}>  $contexts
     */
    private function positionForScope(SettlementPositionScope $scope, array $contexts): SettlementPosition
    {
        $matches = $this->contextsForScope($scope, $contexts);

        if ($scope->type === SettlementPosition::SCOPE_PAYABLE_LINE && $matches === []) {
            return $this->missing(
                SettlementPosition::SCOPE_PAYABLE_LINE,
                (int) $scope->id,
                SettlementPositionIssue::MISSING_PAYABLE_LINE,
                $scope->as_of,
            );
        }

        if ($scope->type === SettlementPosition::SCOPE_PAYABLE_LINE) {
            return $matches[0]['position'];
        }

        if ($scope->type === SettlementPosition::SCOPE_PAYABLE_LINES) {
            foreach ($this->integerIds($scope->payable_line_ids) as $lineId) {
                if (! array_key_exists($lineId, $contexts)) {
                    $matches[] = [
                        'line' => null,
                        'position' => $this->missing(
                            SettlementPosition::SCOPE_PAYABLE_LINE,
                            $lineId,
                            SettlementPositionIssue::MISSING_PAYABLE_LINE,
                            $scope->as_of,
                        ),
                    ];
                }
            }
        }

        if ($scope->type === SettlementPosition::SCOPE_FINANCE_OBLIGATION && $matches === []) {
            $issue = FinanceObligation::query()->whereKey($scope->id)->exists()
                ? SettlementPositionIssue::MISSING_PAYABLE_LINE
                : SettlementPositionIssue::MISSING_FINANCE_OBLIGATION;

            return $this->missing($scope->type, (int) $scope->id, $issue, $scope->as_of);
        }

        if ($matches === []) {
            return $this->emptyScopePosition($scope);
        }

        return $this->aggregateScope($scope, $matches);
    }

    /**
     * @param  array<int, array{line: InvoiceLine, position: SettlementPosition}>  $contexts
     * @return list<array{line: InvoiceLine|null, position: SettlementPosition}>
     */
    private function contextsForScope(SettlementPositionScope $scope, array $contexts): array
    {
        $matches = [];

        foreach ($contexts as $context) {
            $line = $context['line'];
            $charge = $line->charge;
            $obligation = $charge?->financeObligation;

            $matchesScope = match ($scope->type) {
                SettlementPosition::SCOPE_PAYABLE_LINE => (int) $line->id === $scope->id,
                SettlementPosition::SCOPE_PAYABLE_LINES => in_array((int) $line->id, $scope->payable_line_ids, true),
                SettlementPosition::SCOPE_FINANCE_OBLIGATION => (int) $charge?->finance_obligation_id === $scope->id,
                SettlementPosition::SCOPE_INVOICE => (int) $line->invoice_id === $scope->id,
                SettlementPosition::SCOPE_BILLING_ACCOUNT => (int) $obligation?->billing_account_id === $scope->billing_account_id,
                SettlementPosition::SCOPE_FEE_TYPE => (int) $obligation?->billing_account_id === $scope->billing_account_id
                    && $charge?->charge_type === $scope->fee_type,
                default => false,
            };

            if ($matchesScope) {
                $matches[] = $context;
            }
        }

        return $matches;
    }

    /**
     * @param  list<array{line: InvoiceLine|null, position: SettlementPosition}>  $contexts
     */
    private function aggregateScope(SettlementPositionScope $scope, array $contexts): SettlementPosition
    {
        usort($contexts, fn (array $left, array $right): int => $left['position']->scope_id <=> $right['position']->scope_id);
        $breakdown = array_map(fn (array $context): SettlementPosition => $context['position'], $contexts);
        $rawEvidence = SettlementPositionRawEvidence::empty();
        $issues = [];

        foreach ($breakdown as $position) {
            $rawEvidence = new SettlementPositionRawEvidence(
                gross: $rawEvidence->gross->add($position->raw_evidence->gross),
                discount: $rawEvidence->discount->add($position->raw_evidence->discount),
                cash: $rawEvidence->cash->add($position->raw_evidence->cash),
                credit: $rawEvidence->credit->add($position->raw_evidence->credit),
                remaining: $rawEvidence->remaining->add($position->raw_evidence->remaining),
                reversed_discount_residue: $rawEvidence->reversed_discount_residue->add(
                    $position->raw_evidence->reversed_discount_residue,
                ),
                inactive_credit_residue: $rawEvidence->inactive_credit_residue->add(
                    $position->raw_evidence->inactive_credit_residue,
                ),
            );
            array_push($issues, ...$position->issues);
        }

        $billingAccountIds = array_values(array_unique(array_filter(array_map(
            fn (SettlementPosition $position): ?int => $position->billing_account_id,
            $breakdown,
        ))));
        if (count($billingAccountIds) > 1) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::MISMATCHED_BILLING_ACCOUNT,
                ['scope_type' => $scope->type],
            );
        }

        if ($scope->type === SettlementPosition::SCOPE_FINANCE_OBLIGATION && count($breakdown) > 1) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::DUPLICATE_ACTIVE_PAYABLE_LINES,
                ['finance_obligation_id' => (int) $scope->id],
                'INV-3',
            );
        }

        $scopeId = $this->scopeId($scope, $breakdown);
        $capturedAt = $this->capturedAt($scope->as_of);
        $invoiceId = $scope->type === SettlementPosition::SCOPE_INVOICE ? $scope->id : null;
        $billingAccountId = count($billingAccountIds) === 1 ? $billingAccountIds[0] : $scope->billing_account_id;
        $feeType = $scope->type === SettlementPosition::SCOPE_FEE_TYPE ? $scope->fee_type : null;
        $payableLineId = count($breakdown) === 1 ? $breakdown[0]->payable_line_id : null;
        $financeObligationId = $scope->type === SettlementPosition::SCOPE_FINANCE_OBLIGATION ? $scope->id : null;

        if ($issues !== []) {
            return SettlementPosition::invalid(
                scopeType: $scope->type,
                scopeId: $scopeId,
                payableLineId: $payableLineId,
                financeObligationId: $financeObligationId,
                rawEvidence: $rawEvidence,
                issues: $issues,
                invoiceId: $invoiceId,
                billingAccountId: $billingAccountId,
                feeType: $feeType,
                positionMode: $scope->as_of === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
                capturedAt: $capturedAt,
                payableLineBreakdown: $breakdown,
            );
        }

        $amounts = new SettlementPositionAmounts(
            $rawEvidence->gross,
            $rawEvidence->discount,
            $rawEvidence->cash,
            $rawEvidence->credit,
            $rawEvidence->remaining,
        );

        return new SettlementPosition(
            scope_type: $scope->type,
            scope_id: $scopeId,
            payable_line_id: $payableLineId,
            finance_obligation_id: $financeObligationId,
            invoice_id: $invoiceId,
            billing_account_id: $billingAccountId,
            fee_type: $feeType,
            position_mode: $scope->as_of === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
            captured_at: $capturedAt,
            snapshot_version: $this->snapshotVersion($scope->type, $scopeId, $capturedAt, $rawEvidence),
            settlement_state: $this->settlementState($amounts),
            valid: true,
            raw_evidence: $rawEvidence,
            amounts: $amounts,
            issues: [],
            payable_line_breakdown: $breakdown,
        );
    }

    private function emptyScopePosition(SettlementPositionScope $scope): SettlementPosition
    {
        if ($scope->type === SettlementPosition::SCOPE_BILLING_ACCOUNT
            && ! BillingAccount::query()->whereKey($scope->billing_account_id)->exists()) {
            return $this->missing(
                $scope->type,
                (int) $scope->billing_account_id,
                SettlementPositionIssue::MISSING_BILLING_ACCOUNT,
                $scope->as_of,
            );
        }

        return $this->aggregateScope($scope, [[
            'line' => null,
            'position' => new SettlementPosition(
                scope_type: SettlementPosition::SCOPE_PAYABLE_LINE,
                scope_id: 0,
                payable_line_id: null,
                finance_obligation_id: null,
                invoice_id: null,
                billing_account_id: $scope->billing_account_id,
                fee_type: null,
                position_mode: $scope->as_of === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
                captured_at: $this->capturedAt($scope->as_of),
                snapshot_version: 'empty',
                settlement_state: SettlementPosition::STATE_SETTLED_BY_REDUCTION,
                valid: true,
                raw_evidence: SettlementPositionRawEvidence::empty(),
                amounts: new SettlementPositionAmounts(
                    Money::zero(),
                    Money::zero(),
                    Money::zero(),
                    Money::zero(),
                    Money::zero(),
                ),
                issues: [],
            ),
        ]]);
    }

    private function missing(
        string $scopeType,
        int $scopeId,
        string $issueCode,
        ?CarbonImmutable $asOf,
    ): SettlementPosition {
        $capturedAt = $this->capturedAt($asOf);

        return new SettlementPosition(
            scope_type: $scopeType,
            scope_id: $scopeId,
            payable_line_id: null,
            finance_obligation_id: $scopeType === SettlementPosition::SCOPE_FINANCE_OBLIGATION ? $scopeId : null,
            invoice_id: $scopeType === SettlementPosition::SCOPE_INVOICE ? $scopeId : null,
            billing_account_id: $scopeType === SettlementPosition::SCOPE_BILLING_ACCOUNT ? $scopeId : null,
            fee_type: null,
            position_mode: $asOf === null ? SettlementPosition::MODE_CURRENT : SettlementPosition::MODE_AS_OF,
            captured_at: $capturedAt,
            snapshot_version: $this->snapshotVersion($scopeType, $scopeId, $capturedAt, SettlementPositionRawEvidence::empty()),
            settlement_state: SettlementPosition::STATE_MISSING,
            valid: false,
            raw_evidence: SettlementPositionRawEvidence::empty(),
            amounts: null,
            issues: [SettlementPositionIssue::blocking($issueCode, ['scope_id' => $scopeId])],
        );
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, array{amount: mixed, unreliable_status: mixed}>
     */
    private function cashEvidence(array $lineIds, ?CarbonImmutable $asOf): array
    {
        $query = PaymentApplication::query()
            ->join('payments', 'payments.id', '=', 'payment_applications.payment_id')
            ->whereIn('payment_applications.invoice_line_id', $lineIds);

        if ($asOf !== null) {
            $query->where('payments.paid_at', '<=', $asOf)
                ->where('payment_applications.applied_at', '<=', $asOf);
        }

        $amountExpression = $asOf === null
            ? 'COALESCE(SUM(CASE WHEN payments.status = ? THEN payment_applications.amount ELSE 0 END), 0) as amount'
            : 'COALESCE(SUM(payment_applications.amount), 0) as amount';

        $amountBindings = $asOf === null ? [Payment::STATUS_COMPLETED] : [];

        return $query
            ->select('payment_applications.invoice_line_id')
            ->selectRaw($amountExpression, $amountBindings)
            ->selectRaw(
                'MAX(CASE WHEN payments.status != ? THEN 1 ELSE 0 END) as unreliable_status',
                [Payment::STATUS_COMPLETED],
            )
            ->groupBy('payment_applications.invoice_line_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->invoice_line_id => [
                'amount' => $row->amount,
                'unreliable_status' => $row->unreliable_status,
            ]])
            ->all();
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, array{amount: mixed, reversed_residue: mixed, untimestamped_evidence: mixed}>
     */
    private function discountEvidence(array $lineIds, ?CarbonImmutable $asOf): array
    {
        return DiscountAllocation::query()
            ->join('invoice_discounts', 'invoice_discounts.id', '=', 'discount_allocations.invoice_discount_id')
            ->whereIn('discount_allocations.invoice_line_id', $lineIds)
            ->select('discount_allocations.invoice_line_id')
            ->selectRaw('COALESCE(SUM(discount_allocations.amount), 0) as amount')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN invoice_discounts.status = "reversed" THEN discount_allocations.amount ELSE 0 END), 0) as reversed_residue',
            )
            ->selectRaw($asOf === null
                ? '0 as untimestamped_evidence'
                : 'MAX(CASE WHEN discount_allocations.id IS NOT NULL THEN 1 ELSE 0 END) as untimestamped_evidence')
            ->groupBy('discount_allocations.invoice_line_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->invoice_line_id => [
                'amount' => $row->amount,
                'reversed_residue' => $row->reversed_residue,
                'untimestamped_evidence' => $row->untimestamped_evidence,
            ]])
            ->all();
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, array{amount: mixed, inactive_residue: mixed, currencies: mixed}>
     */
    private function creditEvidence(array $lineIds, ?CarbonImmutable $asOf): array
    {
        $query = CreditApplication::query()
            ->join(
                'finance_credit_entitlements',
                'finance_credit_entitlements.id',
                '=',
                'credit_applications.finance_credit_entitlement_id',
            )
            ->whereIn('credit_applications.invoice_line_id', $lineIds);

        if ($asOf !== null) {
            $query->where('credit_applications.applied_at', '<=', $asOf)
                ->whereNotNull('finance_credit_entitlements.approved_at')
                ->where('finance_credit_entitlements.approved_at', '<=', $asOf);
        }

        return $query
            ->select('credit_applications.invoice_line_id')
            ->selectRaw('COALESCE(SUM(credit_applications.amount), 0) as amount')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN finance_credit_entitlements.lifecycle_status != "approved" THEN credit_applications.amount ELSE 0 END), 0) as inactive_residue',
            )
            ->selectRaw('GROUP_CONCAT(DISTINCT finance_credit_entitlements.currency) as currencies')
            ->groupBy('credit_applications.invoice_line_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->invoice_line_id => [
                'amount' => $row->amount,
                'inactive_residue' => $row->inactive_residue,
                'currencies' => $row->currencies,
            ]])
            ->all();
    }

    /**
     * @param  list<SettlementPositionIssue>  $issues
     */
    private function validateLineState(
        InvoiceLine $line,
        ?FinanceCharge $charge,
        ?CarbonImmutable $asOf,
        array &$issues,
    ): void {
        if (! $charge instanceof FinanceCharge) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::FINANCE_CHARGE_NOT_ACTIVE,
                ['payable_line_id' => (int) $line->id],
                'INV-4',
            );

            return;
        }

        if ($asOf !== null) {
            if ($charge->effective_at === null || CarbonImmutable::instance($charge->effective_at)->greaterThan($asOf)) {
                $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::AS_OF_NOT_EFFECTIVE, [
                    'payable_line_id' => (int) $line->id,
                ]);
            }

            if (! $this->wasActiveAt($line->status, $line->voided_at, $asOf)) {
                $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::PAYABLE_LINE_NOT_ACTIVE, [
                    'payable_line_id' => (int) $line->id,
                    'status' => (string) $line->status,
                ]);
            }

            if (! $this->wasActiveAt($charge->status, $charge->voided_at, $asOf)) {
                $issues[] = SettlementPositionIssue::blocking(
                    SettlementPositionIssue::FINANCE_CHARGE_NOT_ACTIVE,
                    ['payable_line_id' => (int) $line->id],
                    'INV-4',
                );
            }

            return;
        }

        if ($line->status !== 'active') {
            $issues[] = SettlementPositionIssue::blocking(SettlementPositionIssue::PAYABLE_LINE_NOT_ACTIVE, [
                'payable_line_id' => (int) $line->id,
                'status' => (string) $line->status,
            ]);
        }

        if ($charge->status !== FinanceCharge::STATUS_ACTIVE) {
            $issues[] = SettlementPositionIssue::blocking(
                SettlementPositionIssue::FINANCE_CHARGE_NOT_ACTIVE,
                ['payable_line_id' => (int) $line->id],
                'INV-4',
            );
        }
    }

    private function wasActiveAt(string $status, mixed $voidedAt, CarbonImmutable $asOf): bool
    {
        if ($status === 'active') {
            return true;
        }

        return $voidedAt !== null && CarbonImmutable::instance($voidedAt)->greaterThan($asOf);
    }

    /**
     * @param  list<SettlementPositionIssue>  $issues
     */
    private function validateRawEvidence(
        Money $gross,
        Money $cash,
        Money $discount,
        Money $credit,
        Money $remaining,
        Money $reversedDiscountResidue,
        Money $inactiveCreditResidue,
        array &$issues,
    ): void {
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
    }

    /**
     * @param  list<int|null>  $ids
     * @return list<int>
     */
    private function integerIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (?int $id): int => (int) $id,
            $ids,
        ), fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<SettlementPosition>  $breakdown
     */
    private function scopeId(SettlementPositionScope $scope, array $breakdown): int
    {
        if ($scope->id !== null) {
            return $scope->id;
        }

        if ($scope->type === SettlementPosition::SCOPE_FEE_TYPE) {
            return (int) $scope->billing_account_id;
        }

        return $breakdown[0]->payable_line_id ?? 0;
    }

    private function snapshotVersion(
        string $scopeType,
        int $scopeId,
        CarbonImmutable $capturedAt,
        SettlementPositionRawEvidence $rawEvidence,
    ): string {
        if ($this->batchSnapshotVersion !== null) {
            return $this->batchSnapshotVersion;
        }

        return hash('sha256', implode('|', [
            $scopeType,
            (string) $scopeId,
            $capturedAt->toIso8601String(),
            $rawEvidence->gross->amount,
            $rawEvidence->discount->amount,
            $rawEvidence->cash->amount,
            $rawEvidence->credit->amount,
            $rawEvidence->remaining->amount,
        ]));
    }

    private function capturedAt(?CarbonImmutable $asOf): CarbonImmutable
    {
        return $asOf ?? $this->batchCapturedAt ?? CarbonImmutable::now();
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
