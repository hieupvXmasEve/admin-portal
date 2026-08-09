<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Queries;

use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Models\MerchandiseVariant;

/**
 * Derives per-campus storefront availability (D2). Never persisted — status
 * is a live SUM() over active variant stock at the viewer's campus, computed
 * on read so a stock change is instantly reflected without a sync step.
 */
class GetMerchandiseAvailabilityQuery
{
    public const AVAILABLE = 'available';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const COMING_SOON = 'coming_soon';

    public const NOT_VISIBLE = 'not_visible';

    public function handle(Merchandise $merchandise, int $campusId): string
    {
        if ($merchandise->status === Merchandise::STATUS_COMING_SOON) {
            return self::COMING_SOON;
        }

        if ($merchandise->status !== Merchandise::STATUS_ACTIVE) {
            return self::NOT_VISIBLE;
        }

        $stock = (int) MerchandiseVariant::query()
            ->where('merchandise_id', $merchandise->id)
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->sum('stock_quantity');

        return $stock > 0 ? self::AVAILABLE : self::OUT_OF_STOCK;
    }
}
