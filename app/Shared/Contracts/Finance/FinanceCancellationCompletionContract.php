<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\FinanceCancellationCompletionData;

interface FinanceCancellationCompletionContract
{
    public function complete(FinanceCancellationCompletionData $completion): void;
}
