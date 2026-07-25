<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentHubAssessmentScoreEvidence
{
    public function __construct(
        public int $id,
        public string $assessmentName,
        public string $assessmentType,
        public ?string $dueDate,
        public ?float $maxPoints,
        public ?int $pointsEarned,
        public ?float $percentageScore,
        public ?string $letterGrade,
        public ?float $gpaPoints,
        public ?string $submittedAt,
        public ?string $gradedAt,
        public bool $isLate,
        public ?string $status,
    ) {}
}
