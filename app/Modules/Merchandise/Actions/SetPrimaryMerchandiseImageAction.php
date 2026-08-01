<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Models\MerchandiseImage;
use Illuminate\Support\Facades\DB;

class SetPrimaryMerchandiseImageAction
{
    public static function run(MerchandiseImage $image): MerchandiseImage
    {
        DB::transaction(function () use ($image) {
            $image->merchandise->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return $image->fresh();
    }
}
