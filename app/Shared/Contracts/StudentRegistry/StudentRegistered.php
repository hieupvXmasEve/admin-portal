<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

final readonly class StudentRegistered
{
    public function __construct(
        public int $studentId,
        public int $campusId,
        public ?int $userId,
    ) {}
}
