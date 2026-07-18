<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class FacultyAccessEligibility
{
    public function __construct(
        public int $lecturerId,
        public int $userId,
        public string $tokenSubjectType,
        public bool $isEligible,
        public string $reason,
        public string $deduplicationKey,
        public string $evaluatedAt,
    ) {}
}
