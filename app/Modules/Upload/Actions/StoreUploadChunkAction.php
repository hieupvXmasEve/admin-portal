<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;

class StoreUploadChunkAction
{
    public static function run(UploadPlatform $uploads, array $data, UploadActor $actor): array
    {
        return $uploads->uploadChunk($data['upload_id'], $data['chunk_index'], $data['chunk'], $actor);
    }
}
