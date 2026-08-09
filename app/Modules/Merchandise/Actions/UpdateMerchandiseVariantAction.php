<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Modules\Merchandise\Models\MerchandiseVariant;

/**
 * Updates variant identity fields only. `stock_quantity` is deliberately
 * excluded — stock changes must go through
 * App\Modules\Merchandise\Support\StockService so every change is locked and
 * gets a StockMovement audit row.
 */
class UpdateMerchandiseVariantAction
{
    /** @param array<string, mixed> $data */
    public static function run(MerchandiseVariant $variant, array $data): MerchandiseVariant
    {
        $variant->update(array_intersect_key($data, array_flip([
            'color', 'size', 'sku', 'is_active',
        ])));

        return $variant->fresh();
    }
}
