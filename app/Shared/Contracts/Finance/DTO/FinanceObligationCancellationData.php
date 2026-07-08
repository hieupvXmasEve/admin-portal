<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class FinanceObligationCancellationData
{
    public function __construct(
        public string $source_system,
        public string $source_kind,
        public string $source_ref,
        public string $obligation_type,
        public string $unpaid_void_reason,
        public string $paid_no_refund_void_reason,
        public bool $require_unpaid_confirmation = false,
        public ?string $unpaid_confirmation = null,
        public ?string $expected_unpaid_confirmation = null,
        public bool $require_paid_no_refund_acknowledgement = false,
        public bool $acknowledge_no_refund = false,
        public ?int $legacy_finance_charge_id = null,
        public ?int $user_id = null,
    ) {}
}
