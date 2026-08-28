<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListRetakeCourseEligibleStudentsQuery
{
    /**
     * Exam-resit statuses meaning a sitting is still pending for a record. While one
     * of these is open, the record's resit path has not been exhausted yet, so the
     * record must not also show up as retake-eligible (cross-lane guard).
     */
    private const RESIT_IN_FLIGHT_STATUSES = [
        ExamResitAttempt::STATUS_REQUESTED,
        ExamResitAttempt::STATUS_APPROVED,
        ExamResitAttempt::STATUS_SCHEDULED,
    ];

    /**
     * Return eligible students for retake course registration.
     *
     * Eligibility:
     * 1. Student.status = 'intake_course'
     * 2. Has AcademicRecord with is_passed = false, completion_status finalized (not in_progress),
     *    and override_pass = false (not overridden to pass)
     * 3. failure_reason routes to the course-retake lane (ACAD-RET-001 Slice 2): grade-only
     *    failures (`grade_failed`) go to exam resit (`thi lại`) instead — UNLESS the record
     *    already has a completed exam-resit attempt and is still failing, meaning the resit
     *    path is exhausted and the record falls back to course retake. Attendance/both/manual
     *    and legacy null failure_reason stay eligible (forward-only).
     * 4. No existing non-terminal course_retake_registrations for same student+unit+semester.
     * 5. No in-flight (not yet completed) exam-resit attempt for the record (cross-lane guard):
     *    while a resit sitting is pending, the record isn't retake-eligible yet.
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
                    ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
                    ->where(fn (Builder $q) => $this->scopeToRetakeLane($q));
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
            ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
            ->where(fn (Builder $q) => $this->scopeToRetakeLane($q))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('unit_id', $id))
            ->when($failedSemesterId, fn (Builder $q, int $id) => $q->where('semester_id', $id))
            ->with(['unit', 'semester'])
            ->get()
            ->groupBy('student_id');

        // Records currently mid-resit (not yet completed) — blocked from retake lane.
        $inFlightResitRecordIds = ExamResitAttempt::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', self::RESIT_IN_FLIGHT_STATUSES)
            ->pluck('academic_record_id');

        // Existing non-terminal retake registrations — one query, matched in memory.
        $activeRegistrations = CourseRetakeRegistration::query()
            ->whereIn('student_id', $studentIds)
            ->when($semesterId, fn (Builder $q, int $id) => $q->where('semester_id', $id))
            ->nonTerminal()
            ->get(['student_id', 'unit_id'])
            ->map(fn ($row) => "{$row->student_id}:{$row->unit_id}")
            ->flip();

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

                if ($inFlightResitRecordIds->contains($record->id)) {
                    continue;
                }

                if ($activeRegistrations->has("{$student->id}:{$record->unit_id}")) {
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

    /**
     * Constrain an AcademicRecord query to failures that belong to the course-retake
     * lane. Grade-only failures (`grade_failed`) are excluded — everything else
     * (attendance/both/manual failures, legacy un-backfilled `null` records) remains
     * eligible — EXCEPT a `grade_failed` record that already sat a completed exam-resit
     * attempt: the resit path is exhausted and still failing, so it falls back into the
     * retake lane. Exam resit no longer excludes these on `failure_reason`
     * (see {@see \App\Modules\Academic\Delivery\Queries\ListExamResitEligibleStudentsQuery}),
     * so a non-grade-only failure can be eligible in both lanes at once; the in-flight-resit
     * guard in {@see handle()} prevents concurrent registration while a resit is pending.
     *
     * @param  Builder<AcademicRecord>  $query
     */
    private function scopeToRetakeLane(Builder $query): void
    {
        $query->where('failure_reason', '!=', AcademicRecord::FAILURE_GRADE_FAILED)
            ->orWhereNull('failure_reason')
            ->orWhereHas('examResitAttempts', function (Builder $q): void {
                $q->where('status', ExamResitAttempt::STATUS_COMPLETED);
            });
    }
}
