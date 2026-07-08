<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;

interface ObligationSettlementReader
{
    public function getSettlement(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): ObligationSettlementResult;

    public function isSettled(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): bool;
}
