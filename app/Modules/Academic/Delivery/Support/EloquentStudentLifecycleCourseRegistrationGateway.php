<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\DTO\StudentLifecycleCourseRegistration;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;

final class EloquentStudentLifecycleCourseRegistrationGateway implements StudentLifecycleCourseRegistrationGateway
{
    public function forStudentSemester(int $studentId, int $semesterId): array
    {
        return CourseRegistration::query()
            ->with(['courseOffering.unit', 'courseOffering.semester'])
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->orderBy('id')
            ->get()
            ->map(static fn (CourseRegistration $registration): StudentLifecycleCourseRegistration => new StudentLifecycleCourseRegistration(
                id: (int) $registration->id,
                courseCode: $registration->courseOffering?->unit?->code ?? 'N/A',
                courseName: $registration->courseOffering?->unit?->name ?? 'N/A',
                semesterName: $registration->courseOffering?->semester?->name ?? 'N/A',
                semesterId: $registration->courseOffering?->semester_id !== null
                    ? (int) $registration->courseOffering->semester_id
                    : null,
                registrationStatus: (string) $registration->registration_status,
            ))
            ->values()
            ->all();
    }

    public function deferableIds(int $studentId, int $semesterId): array
    {
        return CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    public function markDeferred(array $registrationIds): void
    {
        if ($registrationIds === []) {
            return;
        }

        CourseRegistration::query()
            ->whereKey($registrationIds)
            ->update(['registration_status' => 'defer']);
    }
}
