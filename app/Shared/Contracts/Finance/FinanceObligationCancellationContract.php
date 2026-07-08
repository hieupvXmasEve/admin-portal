<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationData;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationResult;

interface FinanceObligationCancellationContract
{
    public function cancel(FinanceObligationCancellationData $data): FinanceObligationCancellationResult;
}
