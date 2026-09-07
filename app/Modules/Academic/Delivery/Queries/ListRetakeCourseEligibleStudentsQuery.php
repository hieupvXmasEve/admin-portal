<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Academic\Delivery\Support\NonCancelledRetakeRegistration;
use App\Modules\Academic\Delivery\Support\OccupiedExamResitAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListRetakeCourseEligibleStudentsQuery
{
    /**
     * Return eligible students for retake course registration.
     *
     * Eligibility:
     * 1. Student.status = 'intake_course'
     * 2. Has AcademicRecord with is_passed = false, completion_status finalized (not in_progress),
     *    grade_status = final, and override_pass = false
     * 3. Any failure_reason (including grade_failed). Staff choose thi lại or học lại.
     * 4. No non-cancelled course_retake_registration for the same original academic record
     *    (including enrolled). Cancelled retakes reopen both lists.
     * 5. No occupied exam-resit for the record (requested/approved/scheduled/
     *    finance_pending_cancellation). Cancelled/completed/no-show do not hide.
     * 6. Open CourseOfferings are attached when available, but are not required at source creation.
     *
     * `semester_id` scopes the *operation* semester (course-offering/registration matching for
     * the retake being created) and does not narrow which students show up. `failed_semester_id`
     * is the distinct filter that narrows the list to students who failed the unit in that
     * specific semester.
     *
     * @param  array{
     *   campus_id?: int|null,
     *   semester_id?: int|null,
     *   failed_semester_id?: int|null,
     *   search?: string|null,
     *   unit_id?: int|null,
     * }  $filters
     * @return Collection<int, array{
     *   student: Student,
     *   failed_record: AcademicRecord,
     *   unit: mixed,
     *   available_offerings: Collection,
     * }>
     */
    public function handle(array $filters): Collection
    {
        $semesterId = $filters['semester_id'] ?? null;
        $failedSemesterId = $filters['failed_semester_id'] ?? null;
        $campusId = $filters['campus_id'] ?? null;
        $search = $filters['search'] ?? null;
        $unitId = $filters['unit_id'] ?? null;

        $students = Student::query()
            ->where('status', 'intake_course')
            ->when($campusId, fn (Builder $q, int $id) => $q->where('campus_id', $id))
            ->when($search, function (Builder $q, string $search): void {
                $q->where(function (Builder $q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                });
            })
            ->whereHas('academicRecords', function (Builder $q) use ($unitId, $failedSemesterId): void {
                $q->where('is_passed', false)
                    ->where('completion_status', '!=', 'in_progress')
                    ->where('grade_status', 'final')
                    ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'));
                if ($unitId) {
                    $q->where('unit_id', $unitId);
                }
                if ($failedSemesterId) {
                    $q->where('semester_id', $failedSemesterId);
                }
            })
            ->with(['campus', 'program'])
            ->get();

        if ($students->isEmpty()) {
            return collect();
        }

        $studentIds = $students->pluck('id');

        // Units already passed per student — excluded from retake eligibility.
        $passedUnitIdsByStudent = AcademicRecord::query()
            ->whereIn('student_id', $studentIds)
            ->where(fn (Builder $q) => $q->where('is_passed', true)->orWhere('override_pass', true))
            ->get(['student_id', 'unit_id'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('unit_id'));

        // Curriculum unit membership per curriculum version — one query for every
        // distinct version instead of one per student.
        $curriculumVersionIds = $students->pluck('curriculum_version_id')->filter()->unique();
        $curriculumUnitIdsByVersion = DB::table('curriculum_units')
            ->whereIn('curriculum_version_id', $curriculumVersionIds)
            ->get(['curriculum_version_id', 'unit_id'])
            ->groupBy('curriculum_version_id')
            ->map(fn (Collection $rows) => $rows->pluck('unit_id'));

        // Candidate failed records for all matched students in one query.
        $failedRecords = AcademicRecord::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_passed', false)
            ->where('completion_status', '!=', 'in_progress')
            ->where('grade_status', 'final')
            ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('unit_id', $id))
            ->when($failedSemesterId, fn (Builder $q, int $id) => $q->where('semester_id', $id))
            ->with(['unit', 'semester'])
            ->get()
            ->groupBy('student_id');

        $occupiedResitRecordIds = OccupiedExamResitAttempt::constrain(
            ExamResitAttempt::query()->whereIn('student_id', $studentIds)
        )->pluck('academic_record_id');

        $occupiedRetakeRecordIds = NonCancelledRetakeRegistration::constrain(
            CourseRetakeRegistration::query()->whereIn('student_id', $studentIds)
        )->pluck('original_academic_record_id');

        // All units that could still need an offering, fetched once and grouped.
        $offeringsByUnit = CourseOffering::query()
            ->where('is_active', true)
            ->where('enrollment_status', 'open')
            ->whereNotIn('course_status', ['completed', 'cancelled'])
            ->whereIn('unit_id', $failedRecords->flatten()->pluck('unit_id')->unique())
            ->when($semesterId, fn (Builder $q, int $id) => $q->where('semester_id', $id))
            ->when($campusId, fn (Builder $q, int $id) => $q->where('campus_id', $id))
            ->with(['semester', 'campus', 'lecture'])
            ->get()
            ->groupBy('unit_id');

        $results = collect();

        foreach ($students as $student) {
            $passedUnitIds = $passedUnitIdsByStudent->get($student->id, collect());
            $curriculumUnitIds = $curriculumUnitIdsByVersion->get($student->curriculum_version_id, collect());

            foreach ($failedRecords->get($student->id, collect()) as $record) {
                if ($passedUnitIds->contains($record->unit_id)) {
                    continue;
                }

                if (! $curriculumUnitIds->contains($record->unit_id)) {
                    continue;
                }

                if ($occupiedResitRecordIds->contains($record->id)) {
                    continue;
                }

                if ($occupiedRetakeRecordIds->contains($record->id)) {
                    continue;
                }

                $results->push([
                    'student' => $student,
                    'failed_record' => $record,
                    'unit' => $record->unit,
                    'available_offerings' => $offeringsByUnit->get($record->unit_id, collect())->values(),
                ]);
            }
        }

        return $results;
    }
}
