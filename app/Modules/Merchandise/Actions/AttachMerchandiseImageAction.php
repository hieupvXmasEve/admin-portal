<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Models\Merchandise;
use App\Models\MerchandiseImage;
use Illuminate\Support\Facades\DB;

class AttachMerchandiseImageAction
{
    /** @param array{sort_order?: ?int, is_primary?: bool} $options */
    public static function run(Merchandise $merchandise, string $path, array $options = []): MerchandiseImage
    {
        return DB::transaction(function () use ($merchandise, $path, $options) {
            $isPrimary = (bool) ($options['is_primary'] ?? false);

            if ($isPrimary) {
                $merchandise->images()->update(['is_primary' => false]);
            }

            $sortOrder = $options['sort_order'] ?? ((int) $merchandise->images()->max('sort_order') + 1);

            return $merchandise->images()->create([
                'path' => $path,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
            ]);
        });
    }
}
