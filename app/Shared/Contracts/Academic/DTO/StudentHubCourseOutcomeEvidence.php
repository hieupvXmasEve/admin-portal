<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentHubCourseOutcomeEvidence
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public int $courseResultId,
        public int $courseOfferingId,
        public int $unitId,
        public ?float $finalPercentage,
        public ?string $finalLetterGrade,
        public ?float $gradePoints,
        public ?string $gradeStatus,
        public ?string $completionStatus,
        public bool $isPassed,
        public ?float $creditPoints,
        public ?float $creditPointsEarned,
        public ?int $attemptNumber,
        public bool $isRepeatCourse,
        public ?bool $meetsAttendanceRequirement,
        public ?string $unitCode = null,
        public ?string $unitName = null,
        public ?string $semesterName = null,
        /** @var array<string, mixed>|null */
        public ?array $gradeDisplay = null,
    ) {}
}
