<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\FinanceCancellationChargeState;

interface FinanceCancellationChargeStateReader
{
    public function forCharge(?int $financeChargeId): FinanceCancellationChargeState;
}
