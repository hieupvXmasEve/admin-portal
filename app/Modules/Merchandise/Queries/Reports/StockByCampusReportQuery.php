<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Queries\Reports;

use App\Models\MerchandiseVariant;
use App\Models\StockMovement;

/**
 * Current stock per variant (live column, campus-scoped) plus recent
 * movement history across those variants. The date range filter applies
 * only to the movement history — current stock is always "now".
 */
class StockByCampusReportQuery
{
    private const MOVEMENT_HISTORY_LIMIT = 100;

    /**
     * @param  list<int>  $campusIds
     * @return array{variants: list<array<string, mixed>>, movements: list<array<string, mixed>>}
     */
    public function handle(array $campusIds, ?string $movementDateFrom, ?string $movementDateTo): array
    {
        if ($campusIds === []) {
            return ['variants' => [], 'movements' => []];
        }

        $variants = MerchandiseVariant::query()
            ->whereIn('campus_id', $campusIds)
            ->with(['merchandise:id,name', 'campus:id,name'])
            ->orderBy('merchandise_id')
            ->get(['id', 'merchandise_id', 'campus_id', 'color', 'size', 'sku', 'stock_quantity', 'is_active'])
            ->toArray();

        $movements = StockMovement::query()
            ->whereHas('variant', fn ($query) => $query->whereIn('campus_id', $campusIds))
            ->when($movementDateFrom, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($movementDateTo, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->with([
                'variant:id,merchandise_id,campus_id,color,size',
                'variant.merchandise:id,name',
                'variant.campus:id,name',
                'performedBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(self::MOVEMENT_HISTORY_LIMIT)
            ->get()
            ->toArray();

        return ['variants' => $variants, 'movements' => $movements];
    }
}
