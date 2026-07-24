<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload;

final readonly class StoredUpload
{
    public function __construct(public int $id) {}
}
