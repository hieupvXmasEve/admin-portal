<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\DTO\SemesterEnrollmentCandidate;
use App\Shared\Contracts\StudentRegistry\DTO\SemesterEnrollmentEligibilityCounts;
use App\Shared\Contracts\StudentRegistry\SemesterEnrollmentEligibilityReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentSemesterEnrollmentEligibilityReader implements SemesterEnrollmentEligibilityReader
{
    private const ELIGIBLE_INTAKE_STATUSES = ['intake_pre_uni_gc', 'intake_course'];

    public function counts(int $campusId, int $semesterId): SemesterEnrollmentEligibilityCounts
    {
        $totalEligible = $this->baseQuery($campusId)->count();

        $enrolled = $this->baseQuery($campusId)
            ->whereHas('enrollments', fn (Builder $query) => $query->where('semester_id', $semesterId))
            ->count();

        $notEnrolled = $this->notEnrolledQuery($campusId, $semesterId)->count();

        return new SemesterEnrollmentEligibilityCounts(
            totalEligible: $totalEligible,
            enrolled: $enrolled,
            notEnrolled: $notEnrolled,
        );
    }

    public function eligibleForSemester(int $campusId, int $semesterId): array
    {
        return $this->notEnrolledQuery($campusId, $semesterId)
            ->get(['id', 'student_id', 'curriculum_version_id'])
            ->map(fn (Student $student): SemesterEnrollmentCandidate => new SemesterEnrollmentCandidate(
                id: (int) $student->id,
                studentCode: (string) $student->student_id,
                curriculumVersionId: $student->curriculum_version_id === null ? null : (int) $student->curriculum_version_id,
            ))
            ->all();
    }

    private function baseQuery(int $campusId): Builder
    {
        return Student::query()
            ->whereIn('status', self::ELIGIBLE_INTAKE_STATUSES)
            ->where('academic_status', 'active')
            ->where('campus_id', $campusId)
            ->whereNotNull('curriculum_version_id');
    }

    private function notEnrolledQuery(int $campusId, int $semesterId): Builder
    {
        return $this->baseQuery($campusId)
            ->whereDoesntHave('enrollments', fn (Builder $query) => $query->where('semester_id', $semesterId))
            ->whereDoesntHave('academicHolds', fn (Builder $query) => $query->where('hold_category', 'registration')->where('status', 'active'));
    }
}
