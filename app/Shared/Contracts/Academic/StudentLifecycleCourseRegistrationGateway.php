<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\AcademicFinanceObligationSource;
use App\Shared\Contracts\Academic\DTO\StudentLifecycleCourseRegistration;

interface StudentLifecycleCourseRegistrationGateway
{
    /** @return list<StudentLifecycleCourseRegistration> */
    public function forStudentSemester(int $studentId, int $semesterId): array;

    /** @return list<int> */
    public function deferableIds(int $studentId, int $semesterId): array;

    /**
     * Return the neutral Finance source references associated with Academic
     * registrations selected for a course-scoped defer.
     *
     * @param  list<int>  $registrationIds
     * @return list<AcademicFinanceObligationSource>
     */
    public function financeObligationSources(array $registrationIds): array;

    /** @param list<int> $registrationIds */
    public function markDeferred(array $registrationIds): void;
}
