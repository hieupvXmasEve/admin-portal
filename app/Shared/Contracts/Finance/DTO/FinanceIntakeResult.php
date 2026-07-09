<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class FinanceIntakeResult
{
    /**
     * @param  list<int>  $credit_application_ids
     */
    public function __construct(
        public string $lifecycle_status,
        public float $amount,
        public string $currency,
        public string $pricing_rule_version,
        public ?int $finance_obligation_id = null,
        public ?int $finance_charge_id = null,
        public ?int $invoice_line_id = null,
        public ?int $finance_credit_entitlement_id = null,
        public array $credit_application_ids = [],
    ) {}
}
