<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Models\Merchandise;

class CreateMerchandiseAction
{
    /**
     * @param  array{name: string, description?: ?string, gold_price: int, status?: ?string}  $data
     */
    public static function run(array $data): Merchandise
    {
        return Merchandise::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'gold_price' => $data['gold_price'],
            'status' => $data['status'] ?? Merchandise::STATUS_ACTIVE,
        ]);
    }
}
