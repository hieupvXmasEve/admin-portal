<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Queries\Reports;

use App\Modules\Merchandise\Models\RedemptionOrder;

/**
 * Gold spend summary derived straight from redemption_orders.total_gold —
 * the ledger (gold_transactions type='redemption'/'redemption_refund') is
 * the source of truth for the wallet balance, but this report reads the
 * order aggregate directly since it is 1:1 with the ledger entries for
 * these two types (RT reconciliation is asserted in feature tests, not
 * re-derived here — no need to duplicate the ledger read).
 */
class GoldUsedAndRefundedReportQuery
{
    /**
     * @param  list<int>  $campusIds
     * @return array{gold_used: int, gold_refunded: int}
     */
    public function handle(array $campusIds, ?string $dateFrom, ?string $dateTo): array
    {
        if ($campusIds === []) {
            return ['gold_used' => 0, 'gold_refunded' => 0];
        }

        $scoped = fn () => RedemptionOrder::query()
            ->whereIn('campus_id', $campusIds)
            ->when($dateFrom, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($dateTo, fn ($query, $to) => $query->where('created_at', '<=', $to));

        $goldUsed = (int) $scoped()->whereIn('status', RedemptionOrder::NON_REVERSED_STATUSES)->sum('total_gold');
        $goldRefunded = (int) $scoped()->whereNotIn('status', RedemptionOrder::NON_REVERSED_STATUSES)->sum('total_gold');

        return ['gold_used' => $goldUsed, 'gold_refunded' => $goldRefunded];
    }
}
