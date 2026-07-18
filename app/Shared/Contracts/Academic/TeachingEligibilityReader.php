<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\CourseOfferingUnitReference;
use App\Shared\Contracts\Academic\DTO\TeachingEligibility;

interface TeachingEligibilityReader
{
    /**
     * Returns the Workforce-owned eligibility fact for a Faculty Member.
     * A null response means that the supplied reference is not a Faculty Member.
     */
    public function forLecturerAndUnit(int $lecturerId, CourseOfferingUnitReference $unit): ?TeachingEligibility;
}
