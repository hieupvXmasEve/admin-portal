<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

final readonly class SettlementPositionIssue
{
    public const MISSING_PAYABLE_LINE = 'settlement_position.missing_payable_line';

    public const BATCH_CARDINALITY_MISMATCH = 'settlement_position.batch_cardinality_mismatch';

    public const MISSING_FINANCE_OBLIGATION = 'settlement_position.missing_finance_obligation';

    public const MISSING_BILLING_ACCOUNT = 'settlement_position.missing_billing_account';

    public const PAYABLE_LINE_NOT_ACTIVE = 'settlement_position.payable_line_not_active';

    public const PAYABLE_LINE_NOT_COLLECTIBLE = 'settlement_position.payable_line_not_collectible';

    public const FINANCE_CHARGE_NOT_ACTIVE = 'settlement_position.finance_charge_not_active';

    public const DUPLICATE_ACTIVE_PAYABLE_LINES = 'settlement_position.duplicate_active_payable_lines';

    public const UNSUPPORTED_CURRENCY = 'settlement_position.unsupported_currency';

    public const MISSING_CURRENCY = 'settlement_position.missing_currency';

    public const NEGATIVE_CASH_APPLICATION = 'settlement_position.negative_cash_application';

    public const NEGATIVE_DISCOUNT_ALLOCATION = 'settlement_position.negative_discount_allocation';

    public const NEGATIVE_CREDIT_APPLICATION = 'settlement_position.negative_credit_application';

    public const REVERSED_DISCOUNT_RESIDUE = 'settlement_position.reversed_discount_residue';

    public const INACTIVE_CREDIT_RESIDUE = 'settlement_position.inactive_credit_residue';

    public const DISCOUNT_EXCEEDS_GROSS = 'settlement_position.discount_exceeds_gross';

    public const CASH_EXCEEDS_NET_DUE = 'settlement_position.cash_exceeds_net_due';

    public const CREDIT_EXCEEDS_REMAINING = 'settlement_position.credit_exceeds_remaining';

    public const NEGATIVE_RAW_REMAINING = 'settlement_position.negative_raw_remaining';

    public const MISMATCHED_BILLING_ACCOUNT = 'settlement_position.mismatched_billing_account';

    public const AS_OF_NOT_EFFECTIVE = 'settlement_position.as_of_not_effective';

    public const AS_OF_UNTIMESTAMPED_DISCOUNT_EVIDENCE = 'settlement_position.as_of_untimestamped_discount_evidence';

    public const AS_OF_UNRELIABLE_PAYMENT_STATUS = 'settlement_position.as_of_unreliable_payment_status';

    /**
     * @param  array<string, int|string>  $evidence
     */
    public function __construct(
        public string $code,
        public string $severity,
        public bool $blocking,
        public array $evidence = [],
        public ?string $finance_invariant_code = null,
    ) {}

    /**
     * @param  array<string, int|string>  $evidence
     */
    public static function blocking(
        string $code,
        array $evidence = [],
        ?string $financeInvariantCode = null,
    ): self {
        return new self(
            code: $code,
            severity: 'blocking',
            blocking: true,
            evidence: $evidence,
            finance_invariant_code: $financeInvariantCode,
        );
    }
}
