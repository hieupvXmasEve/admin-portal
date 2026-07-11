<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use Carbon\CarbonImmutable;

final readonly class SettlementPosition
{
    public const SCOPE_PAYABLE_LINE = 'payable_line';

    public const SCOPE_FINANCE_OBLIGATION = 'finance_obligation';

    public const SCOPE_INVOICE = 'invoice';

    public const SCOPE_FEE_TYPE = 'fee_type';

    public const SCOPE_BILLING_ACCOUNT = 'billing_account';

    public const SCOPE_PAYABLE_LINES = 'payable_lines';

    public const STATE_MISSING = 'missing';

    public const STATE_INVALID = 'invalid';

    public const STATE_UNPAID = 'unpaid';

    public const STATE_PARTIALLY_SETTLED = 'partially_settled';

    public const STATE_SETTLED_BY_CASH = 'settled_by_cash';

    public const STATE_SETTLED_BY_REDUCTION = 'settled_by_reduction';

    public const MODE_CURRENT = 'current';

    public const MODE_AS_OF = 'as_of';

    /**
     * @param  list<SettlementPositionIssue>  $issues
     * @param  list<SettlementPosition>  $payableLineBreakdown
     */
    public function __construct(
        public string $scope_type,
        public int $scope_id,
        public ?int $payable_line_id,
        public ?int $finance_obligation_id,
        public ?int $invoice_id,
        public ?int $billing_account_id,
        public ?string $fee_type,
        public string $position_mode,
        public CarbonImmutable $captured_at,
        public string $snapshot_version,
        public string $settlement_state,
        public bool $valid,
        public SettlementPositionRawEvidence $raw_evidence,
        public ?SettlementPositionAmounts $amounts,
        public array $issues,
        public array $payable_line_breakdown = [],
    ) {}

    /**
     * @param  list<SettlementPositionIssue>  $issues
     * @param  list<SettlementPosition>  $payableLineBreakdown
     */
    public static function invalid(
        string $scopeType,
        int $scopeId,
        ?int $payableLineId,
        ?int $financeObligationId,
        SettlementPositionRawEvidence $rawEvidence,
        array $issues,
        ?int $invoiceId = null,
        ?int $billingAccountId = null,
        ?string $feeType = null,
        string $positionMode = self::MODE_CURRENT,
        ?CarbonImmutable $capturedAt = null,
        array $payableLineBreakdown = [],
    ): self {
        $capturedAt ??= CarbonImmutable::now();

        return new self(
            scope_type: $scopeType,
            scope_id: $scopeId,
            payable_line_id: $payableLineId,
            finance_obligation_id: $financeObligationId,
            invoice_id: $invoiceId,
            billing_account_id: $billingAccountId,
            fee_type: $feeType,
            position_mode: $positionMode,
            captured_at: $capturedAt,
            snapshot_version: self::snapshotVersion($scopeType, $scopeId, $positionMode, $capturedAt, $rawEvidence),
            settlement_state: self::STATE_INVALID,
            valid: false,
            raw_evidence: $rawEvidence,
            amounts: null,
            issues: $issues,
            payable_line_breakdown: $payableLineBreakdown,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function hasIssue(string $code): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->code === $code) {
                return true;
            }
        }

        return false;
    }

    private static function snapshotVersion(
        string $scopeType,
        int $scopeId,
        string $positionMode,
        CarbonImmutable $capturedAt,
        SettlementPositionRawEvidence $rawEvidence,
    ): string {
        return hash('sha256', implode('|', [
            $scopeType,
            (string) $scopeId,
            $positionMode,
            $capturedAt->toIso8601String(),
            $rawEvidence->gross->amount,
            $rawEvidence->discount->amount,
            $rawEvidence->cash->amount,
            $rawEvidence->credit->amount,
            $rawEvidence->remaining->amount,
        ]));
    }
}
