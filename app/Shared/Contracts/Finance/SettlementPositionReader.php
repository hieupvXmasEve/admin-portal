<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use Carbon\CarbonImmutable;

/**
 * Finance-owned contract for canonical settlement positions.
 *
 * Other contexts receive narrower adapters rather than this payable-line
 * ledger contract.
 */
interface SettlementPositionReader
{
    public function forPayableLine(int $payableLineId, ?CarbonImmutable $asOf = null): SettlementPosition;

    public function forFinanceObligation(int $financeObligationId, ?CarbonImmutable $asOf = null): SettlementPosition;

    public function forInvoice(int $invoiceId, ?CarbonImmutable $asOf = null): SettlementPosition;

    public function forFeeType(
        int $billingAccountId,
        string $feeType,
        ?CarbonImmutable $asOf = null,
    ): SettlementPosition;

    public function forBillingAccount(int $billingAccountId, ?CarbonImmutable $asOf = null): SettlementPosition;

    /**
     * @param  list<int>  $payableLineIds
     */
    public function forPayableLines(array $payableLineIds, ?CarbonImmutable $asOf = null): SettlementPosition;

    /**
     * @param  list<SettlementPositionScope>  $scopes
     * @return list<SettlementPosition>
     */
    public function batch(array $scopes): array;
}
