<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

final readonly class UploadActor
{
    public function __construct(
        public ?int $userId = null,
        public ?int $studentId = null,
    ) {}
}
