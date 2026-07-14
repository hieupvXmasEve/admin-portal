<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class ObligationSettlementResult
{
    public const STATE_MISSING_FINANCE_OBLIGATION = 'missing_finance_obligation';

    public const STATE_INVALID_SETTLEMENT_POSITION = 'invalid_settlement_position';

    public const STATE_UNPAID = 'unpaid';

    public const STATE_PARTIALLY_PAID = 'partially_paid';

    public const STATE_PAID = 'paid';

    public const STATE_OVERPAID = 'overpaid';

    public const STATE_SETTLED_BY_DISCOUNT_OR_CREDIT = 'settled_by_discount_or_credit';

    public function __construct(
        public string $source_system,
        public string $source_kind,
        public string $source_ref,
        public string $obligation_type,
        public ?int $finance_obligation_id,
        public string $settlement_state,
        public float $payable,
        public float $paid,
        public float $discount,
        public float $outstanding,
        /** @var list<string> */
        public array $settlement_position_issue_codes = [],
    ) {}

    public static function missing(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): self {
        return new self(
            source_system: $sourceSystem,
            source_kind: $sourceKind,
            source_ref: $sourceRef,
            obligation_type: $obligationType,
            finance_obligation_id: null,
            settlement_state: self::STATE_MISSING_FINANCE_OBLIGATION,
            payable: 0.0,
            paid: 0.0,
            discount: 0.0,
            outstanding: 0.0,
        );
    }

    /**
     * @param  list<string>  $issueCodes
     */
    public static function invalid(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
        ?int $financeObligationId,
        array $issueCodes,
    ): self {
        return new self(
            source_system: $sourceSystem,
            source_kind: $sourceKind,
            source_ref: $sourceRef,
            obligation_type: $obligationType,
            finance_obligation_id: $financeObligationId,
            settlement_state: self::STATE_INVALID_SETTLEMENT_POSITION,
            payable: 0.0,
            paid: 0.0,
            discount: 0.0,
            outstanding: 0.0,
            settlement_position_issue_codes: $issueCodes,
        );
    }

    public function isSettled(): bool
    {
        return in_array($this->settlement_state, [
            self::STATE_PAID,
            self::STATE_OVERPAID,
            self::STATE_SETTLED_BY_DISCOUNT_OR_CREDIT,
        ], true);
    }
}
