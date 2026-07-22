<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;

class CancelChunkedUploadAction
{
    public static function run(UploadPlatform $uploads, string $uploadId, UploadActor $actor): bool
    {
        return $uploads->cancelUpload($uploadId, $actor);
    }
}
