<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Models\Merchandise;
use App\Models\MerchandiseImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReorderMerchandiseImagesAction
{
    /**
     * @param  list<int>  $orderedImageIds
     * @return Collection<int, MerchandiseImage>
     */
    public static function run(Merchandise $merchandise, array $orderedImageIds): Collection
    {
        DB::transaction(function () use ($merchandise, $orderedImageIds) {
            foreach ($orderedImageIds as $index => $imageId) {
                $merchandise->images()->whereKey($imageId)->update(['sort_order' => $index]);
            }
        });

        return $merchandise->images()->orderBy('sort_order')->get();
    }
}
