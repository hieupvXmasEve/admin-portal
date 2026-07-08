<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

use App\Shared\Contracts\Finance\Enums\FinancialEffect;

final readonly class FinanceIntakeData
{
    /**
     * @param  array<string, mixed>  $facts
     */
    public function __construct(
        public string $source_system,
        public string $source_kind,
        public string $source_ref,
        public FinancialEffect $financial_effect,
        public string $obligation_type,
        public array $facts,
    ) {}
}
