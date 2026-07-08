<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class FinanceObligationCancellationResult
{
    public const FEE_DISPOSITION_NO_CHARGE = 'no_charge';

    public const FEE_DISPOSITION_VOIDED_UNPAID_CHARGE = 'voided_unpaid_charge';

    public const FEE_DISPOSITION_KEPT_PAID_NO_REFUND = 'kept_paid_no_refund';

    public function __construct(
        public string $fee_disposition,
        public bool $is_paid,
        public bool $had_unpaid_charge,
        public ?int $finance_charge_id = null,
    ) {}
}
