<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

/**
 * Read-only legacy Course Result evidence used solely by transcript-backfill
 * preflight. Date-only finalization evidence is intentionally not promoted to
 * a timestamp without the issue 24 human approval checkpoint.
 */
final readonly class LegacyTranscriptOutcome
{
    public function __construct(
        public int $courseResultId,
        public ?int $studentId,
        public ?int $courseOfferingId,
        public ?int $semesterId,
        public ?int $unitId,
        public ?int $programId,
        public ?int $campusId,
        public ?int $attemptNumber,
        public ?float $finalPercentage,
        public ?string $finalLetterGrade,
        public ?float $creditPoints,
        public ?float $creditPointsEarned,
        public ?float $qualityPoints,
        public ?bool $isPassed,
        public ?bool $excludedFromGpa,
        public ?bool $affectsAcademicStanding,
        public ?bool $affectsGraduationRequirement,
        public ?bool $satisfiesPrerequisite,
        public ?string $finalizedOn,
    ) {}
}
