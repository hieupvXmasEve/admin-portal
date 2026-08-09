<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Actions;

use App\Modules\Merchandise\Models\MerchandiseImage;

class DetachMerchandiseImageAction
{
    public static function run(MerchandiseImage $image): void
    {
        // ponytail: metadata-only detach, does not delete the underlying
        // uploaded file. The Upload module's own orphan cleanup job
        // (App\Modules\Upload\Jobs\CleanupOrphanedFilesJob) reclaims storage
        // for files no longer referenced by any record.
        $image->delete();
    }
}
