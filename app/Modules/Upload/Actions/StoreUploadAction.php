<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Models\UploadRecord;
use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Http\UploadedFile;

class StoreUploadAction
{
    public static function run(
        UploadPlatform $uploads,
        UploadedFile $file,
        string $context,
        UploadActor $actor,
        array $metadata = [],
    ): UploadRecord {
        return $uploads->upload(
            $file,
            $context,
            $actor->userId,
            $actor->studentId,
            $metadata,
        );
    }
}
