<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class LecturerAccessGrant
{
    public function __construct(
        public int $userId,
        public int $lecturerId,
        public string $status,
        public string $reason,
    ) {}
}
