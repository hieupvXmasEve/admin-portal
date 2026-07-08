<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class FinanceIntakeResult
{
    public function __construct(
        public int $finance_obligation_id,
        public int $finance_charge_id,
        public int $invoice_line_id,
        public string $lifecycle_status,
        public float $amount,
        public string $currency,
        public string $pricing_rule_version,
    ) {}
}
