<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;

/**
 * Finance-owned contract for canonical settlement positions.
 *
 * Other contexts receive narrower adapters rather than this payable-line
 * ledger contract.
 */
interface SettlementPositionReader
{
    public function forPayableLine(int $payableLineId): SettlementPosition;

    public function forFinanceObligation(int $financeObligationId): SettlementPosition;
}
