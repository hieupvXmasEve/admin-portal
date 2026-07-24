<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface CourseRosterReader
{
    /** @return list<int> */
    public function activeStudentIds(int $courseOfferingId): array;

    /**
     * @return list<int>
     */
    public function studentIdsForAcademicPeriod(int $academicPeriodId, ?int $campusId = null): array;

    public function studentHasVisibleRegistration(int $studentId, int $courseOfferingId): bool;
}
