<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\SemesterEnrollmentCandidate;
use App\Shared\Contracts\StudentRegistry\DTO\SemesterEnrollmentEligibilityCounts;

interface SemesterEnrollmentEligibilityReader
{
    public function counts(int $campusId, int $semesterId): SemesterEnrollmentEligibilityCounts;

    /**
     * Students eligible for a new enrollment in the given semester: active,
     * intake-eligible status, has a curriculum version, not already
     * enrolled this semester, and no active registration hold.
     *
     * @return list<SemesterEnrollmentCandidate>
     */
    public function eligibleForSemester(int $campusId, int $semesterId): array;
}
