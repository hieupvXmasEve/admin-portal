<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Models\UploadRecord;
use App\Modules\Upload\Support\UploadPlatform;

class DeleteUploadAction
{
    public static function run(UploadPlatform $uploads, UploadRecord $uploadRecord): bool
    {
        return $uploads->delete($uploadRecord);
    }
}
