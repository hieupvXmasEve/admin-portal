<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use Carbon\CarbonImmutable;

final readonly class SettlementPositionScope
{
    /**
     * @param  list<int>  $payable_line_ids
     */
    private function __construct(
        public string $type,
        public ?int $id,
        public ?int $billing_account_id,
        public ?string $fee_type,
        public array $payable_line_ids,
        public ?CarbonImmutable $as_of,
    ) {}

    public static function payableLine(int $payableLineId, ?CarbonImmutable $asOf = null): self
    {
        return new self(SettlementPosition::SCOPE_PAYABLE_LINE, $payableLineId, null, null, [], $asOf);
    }

    public static function financeObligation(int $financeObligationId, ?CarbonImmutable $asOf = null): self
    {
        return new self(SettlementPosition::SCOPE_FINANCE_OBLIGATION, $financeObligationId, null, null, [], $asOf);
    }

    public static function invoice(int $invoiceId, ?CarbonImmutable $asOf = null): self
    {
        return new self(SettlementPosition::SCOPE_INVOICE, $invoiceId, null, null, [], $asOf);
    }

    public static function feeType(int $billingAccountId, string $feeType, ?CarbonImmutable $asOf = null): self
    {
        return new self(SettlementPosition::SCOPE_FEE_TYPE, null, $billingAccountId, $feeType, [], $asOf);
    }

    public static function billingAccount(int $billingAccountId, ?CarbonImmutable $asOf = null): self
    {
        return new self(SettlementPosition::SCOPE_BILLING_ACCOUNT, $billingAccountId, $billingAccountId, null, [], $asOf);
    }

    /**
     * @param  list<int>  $payableLineIds
     */
    public static function payableLines(array $payableLineIds, ?CarbonImmutable $asOf = null): self
    {
        return new self(SettlementPosition::SCOPE_PAYABLE_LINES, null, null, null, $payableLineIds, $asOf);
    }
}
