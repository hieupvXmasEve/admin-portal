<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

/**
 * Delivery-owned final outcome for one student attempt in a course offering.
 *
 * This deliberately contains only the published outcome. Assessment components,
 * score details, and attendance evidence remain private to Course Delivery.
 */
final readonly class CourseResult
{
    public function __construct(
        public int $courseResultId,
        public int $studentId,
        public int $courseOfferingId,
        public int $semesterId,
        public int $unitId,
        public int $programId,
        public int $campusId,
        public int $attemptNumber,
        public float $finalPercentage,
        public string $finalLetterGrade,
        public float $creditPoints,
        public float $creditPointsEarned,
        public float $qualityPoints,
        public bool $isPassed,
        public bool $excludedFromGpa,
        public bool $affectsAcademicStanding,
        public bool $affectsGraduationRequirement,
        public bool $satisfiesPrerequisite,
        public ?string $finalizedAt,
    ) {}
}
