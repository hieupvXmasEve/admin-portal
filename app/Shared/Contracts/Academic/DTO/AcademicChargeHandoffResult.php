<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicChargeHandoffResult
{
    public function __construct(
        public int $source_id,
        public string $source_kind,
        public string $source_ref,
        public string $obligation_type,
        public string $status,
        public string $hq_fee_status,
        public ?int $finance_obligation_id,
        public ?int $finance_charge_id,
    ) {}
}
