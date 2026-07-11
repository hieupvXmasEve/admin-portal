<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use Carbon\CarbonImmutable;

final readonly class SettlementPosition
{
    public const SCOPE_PAYABLE_LINE = 'payable_line';

    public const SCOPE_FINANCE_OBLIGATION = 'finance_obligation';

    public const STATE_MISSING = 'missing';

    public const STATE_INVALID = 'invalid';

    public const STATE_UNPAID = 'unpaid';

    public const STATE_PARTIALLY_SETTLED = 'partially_settled';

    public const STATE_SETTLED_BY_CASH = 'settled_by_cash';

    public const STATE_SETTLED_BY_REDUCTION = 'settled_by_reduction';

    public const MODE_CURRENT = 'current';

    /**
     * @param  list<SettlementPositionIssue>  $issues
     */
    public function __construct(
        public string $scope_type,
        public int $scope_id,
        public ?int $payable_line_id,
        public ?int $finance_obligation_id,
        public string $position_mode,
        public CarbonImmutable $captured_at,
        public string $settlement_state,
        public bool $valid,
        public SettlementPositionRawEvidence $raw_evidence,
        public ?SettlementPositionAmounts $amounts,
        public array $issues,
    ) {}

    /**
     * @param  list<SettlementPositionIssue>  $issues
     */
    public static function invalid(
        string $scopeType,
        int $scopeId,
        ?int $payableLineId,
        ?int $financeObligationId,
        SettlementPositionRawEvidence $rawEvidence,
        array $issues,
    ): self {
        return new self(
            scope_type: $scopeType,
            scope_id: $scopeId,
            payable_line_id: $payableLineId,
            finance_obligation_id: $financeObligationId,
            position_mode: self::MODE_CURRENT,
            captured_at: CarbonImmutable::now(),
            settlement_state: self::STATE_INVALID,
            valid: false,
            raw_evidence: $rawEvidence,
            amounts: null,
            issues: $issues,
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
}
