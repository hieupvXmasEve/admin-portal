<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;

class StoreMultipleUploadsAction
{
    public static function run(
        UploadPlatform $uploads,
        array $files,
        string $context,
        UploadActor $actor,
        array $metadata = [],
    ): array {
        return $uploads->uploadMultiple(
            $files,
            $context,
            $actor->userId,
            $actor->studentId,
            $metadata,
        );
    }
}
