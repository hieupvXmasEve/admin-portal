<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentDeferActionSummary
{
    public function __construct(
        public int $id,
        public int $studentId,
        public ?string $studentCode,
        public ?string $studentName,
        public ?int $campusId,
        public bool $currentlyDeferred,
        public ?int $fromSemesterId,
        public ?int $returnSemesterId,
        public ?string $effectiveAt,
        public ?string $signedAt,
        public ?int $changedByUserId,
        public ?string $createdAt,
        public ?string $notes,
    ) {}
}
