<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AcademicRecord;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Academic\DTO\AcademicFinanceObligationSource;
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

    public function financeObligationSources(array $registrationIds): array
    {
        $registrationIds = array_values(array_unique(array_filter(array_map('intval', $registrationIds))));
        if ($registrationIds === []) {
            return [];
        }

        $sources = collect($registrationIds)->map(
            static fn (int $registrationId): AcademicFinanceObligationSource => new AcademicFinanceObligationSource(
                source_system: 'finance',
                source_kind: 'legacy_course_registration',
                source_ref: "legacy-course-registration:{$registrationId}",
                obligation_type: AcademicFinanceSourceKeys::RETAKE_FEE,
            ),
        );

        return $sources
            ->concat(CourseRetakeRegistration::query()
                ->whereIn('course_registration_id', $registrationIds)
                ->pluck('id')
                ->map(static fn (int|string $retakeRegistrationId): AcademicFinanceObligationSource => new AcademicFinanceObligationSource(
                    source_system: AcademicFinanceSourceKeys::SOURCE_SYSTEM,
                    source_kind: AcademicFinanceSourceKeys::COURSE_RETAKE_REGISTRATION,
                    source_ref: AcademicFinanceSourceKeys::courseRetakeRegistrationRef((int) $retakeRegistrationId),
                    obligation_type: AcademicFinanceSourceKeys::RETAKE_FEE,
                )))
            ->unique(static fn (AcademicFinanceObligationSource $source): string => implode('|', [
                $source->source_system,
                $source->source_kind,
                $source->source_ref,
                $source->obligation_type,
            ]))
            ->values()
            ->all();
    }

    public function markDeferred(array $registrationIds): void
    {
        if ($registrationIds === []) {
            return;
        }

        $registrations = CourseRegistration::query()
            ->whereKey($registrationIds)
            ->get(['id', 'student_id', 'course_offering_id']);

        CourseRegistration::query()
            ->whereKey($registrationIds)
            ->update(['registration_status' => 'defer']);

        // Stale academic_records for the deferred course must not survive the
        // defer — finalize-course reads academic_records unscoped by
        // registration_status, so a leftover row here is what let a later
        // finalize silently flip a deferred registration back to 'completed'.
        $registrations->groupBy('student_id')->each(
            static fn ($group, int $studentId) => AcademicRecord::query()
                ->where('student_id', $studentId)
                ->whereIn('course_offering_id', $group->pluck('course_offering_id'))
                ->delete()
        );
    }
}
