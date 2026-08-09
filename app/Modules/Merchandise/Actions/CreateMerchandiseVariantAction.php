<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Models\MerchandiseVariant;

class CreateMerchandiseVariantAction
{
    /**
     * @param  array{campus_id: int, color?: ?string, size?: ?string, sku?: ?string, stock_quantity?: ?int, is_active?: ?bool}  $data
     */
    public static function run(Merchandise $merchandise, array $data): MerchandiseVariant
    {
        return MerchandiseVariant::create([
            'merchandise_id' => $merchandise->id,
            'campus_id' => $data['campus_id'],
            'color' => $data['color'] ?? null,
            'size' => $data['size'] ?? null,
            'sku' => $data['sku'] ?? null,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
