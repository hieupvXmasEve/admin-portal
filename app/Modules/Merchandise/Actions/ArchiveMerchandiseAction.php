<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Modules\Merchandise\Models\Merchandise;

/**
 * Archiving hides an item from the store without deleting it. Redemption
 * order lines (Phase 3) snapshot the item's name/price at order time, so old
 * orders keep displaying correctly after the source item is archived.
 */
class ArchiveMerchandiseAction
{
    public static function run(Merchandise $merchandise): Merchandise
    {
        $merchandise->update(['status' => Merchandise::STATUS_ARCHIVED]);

        return $merchandise->fresh();
    }
}
