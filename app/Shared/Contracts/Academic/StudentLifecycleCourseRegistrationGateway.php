<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentLifecycleCourseRegistration;

interface StudentLifecycleCourseRegistrationGateway
{
    /** @return list<StudentLifecycleCourseRegistration> */
    public function forStudentSemester(int $studentId, int $semesterId): array;

    /** @return list<int> */
    public function deferableIds(int $studentId, int $semesterId): array;

    /** @param list<int> $registrationIds */
    public function markDeferred(array $registrationIds): void;
}
