<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Queries\Reports;

use App\Models\RedemptionOrder;
use App\Models\RedemptionOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Top-N merchandise by redeemed quantity, aggregated from
 * redemption_order_items. Only items belonging to NON_REVERSED orders count
 * — a rejected/cancelled order never actually left the shelf, so it should
 * not inflate a "most redeemed" ranking (mirrors the "Gold used" scope in
 * GoldUsedAndRefundedReportQuery).
 */
class MostRedeemedMerchandiseReportQuery
{
    private const DEFAULT_LIMIT = 10;

    /**
     * @param  list<int>  $campusIds
     * @return list<array{merchandise_id: int, merchandise_name: string, total_quantity: int, order_count: int}>
     */
    public function handle(array $campusIds, ?string $dateFrom, ?string $dateTo, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($campusIds === []) {
            return [];
        }

        return RedemptionOrderItem::query()
            ->join('redemption_orders', 'redemption_orders.id', '=', 'redemption_order_items.redemption_order_id')
            ->join('merchandise_variants', 'merchandise_variants.id', '=', 'redemption_order_items.merchandise_variant_id')
            ->join('merchandise', 'merchandise.id', '=', 'merchandise_variants.merchandise_id')
            ->whereIn('redemption_orders.campus_id', $campusIds)
            ->whereIn('redemption_orders.status', RedemptionOrder::NON_REVERSED_STATUSES)
            ->when($dateFrom, fn ($query, $from) => $query->where('redemption_orders.created_at', '>=', $from))
            ->when($dateTo, fn ($query, $to) => $query->where('redemption_orders.created_at', '<=', $to))
            ->groupBy('merchandise.id', 'merchandise.name')
            ->select([
                'merchandise.id as merchandise_id',
                'merchandise.name as merchandise_name',
                DB::raw('SUM(redemption_order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT redemption_order_items.redemption_order_id) as order_count'),
            ])
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'merchandise_id' => (int) $row->merchandise_id,
                'merchandise_name' => (string) $row->merchandise_name,
                'total_quantity' => (int) $row->total_quantity,
                'order_count' => (int) $row->order_count,
            ])
            ->all();
    }
}
