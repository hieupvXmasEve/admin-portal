<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\CourseRosterReader;

final class EloquentCourseRosterReader implements CourseRosterReader
{
    /** @return list<int> */
    public function activeStudentIds(int $courseOfferingId): array
    {
        return CourseRegistration::query()
            ->where('course_offering_id', $courseOfferingId)
            ->activeForClassRoster()
            ->orderBy('id')
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
    }

    public function studentIdsForAcademicPeriod(int $academicPeriodId, ?int $campusId = null): array
    {
        return CourseRegistration::query()
            ->whereHas('courseOffering', fn ($offerings) => $offerings->where('semester_id', $academicPeriodId))
            ->when($campusId !== null, fn ($registrations) => $registrations->whereHas(
                'student',
                fn ($students) => $students->where('campus_id', $campusId),
            ))
            ->distinct()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
    }

    public function studentHasVisibleRegistration(int $studentId, int $courseOfferingId): bool
    {
        return CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->exists();
    }
}
