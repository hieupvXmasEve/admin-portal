<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Modules\Merchandise\Models\Merchandise;

class UpdateMerchandiseAction
{
    /** @param array<string, mixed> $data */
    public static function run(Merchandise $merchandise, array $data): Merchandise
    {
        $merchandise->update(array_intersect_key($data, array_flip([
            'name', 'description', 'gold_price', 'status',
        ])));

        return $merchandise->fresh();
    }
}
