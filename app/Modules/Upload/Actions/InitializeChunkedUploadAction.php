<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;

class InitializeChunkedUploadAction
{
    public static function run(UploadPlatform $uploads, array $data, UploadActor $actor): array
    {
        return $uploads->initializeUpload(
            $data['filename'],
            $data['file_size'],
            $data['context'],
            $actor->userId,
            $actor->studentId,
            $data['metadata'] ?? [],
        );
    }
}
