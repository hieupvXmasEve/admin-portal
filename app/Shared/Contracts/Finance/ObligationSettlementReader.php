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

    /**
     * Ledger-settled only (payment applications / discounts). Does not count paid DNG
     * before the payment bridge allocates cash to invoice lines.
     */
    public function isSettled(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): bool;

    /**
     * Ledger settled OR external paid evidence (paid DNG not yet bridged).
     * Use for display/cancel gates; do not use for auto-enroll/sync side effects.
     */
    public function isSettledOrExternallyPaid(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): bool;
}
