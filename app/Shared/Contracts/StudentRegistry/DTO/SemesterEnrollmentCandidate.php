<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

readonly class SemesterEnrollmentCandidate
{
    public function __construct(
        public int $id,
        public string $studentCode,
        public ?int $curriculumVersionId,
    ) {}
}
