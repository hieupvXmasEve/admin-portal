<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListRetakeCourseEligibleStudentsQuery
{
    /**
     * Return eligible students for retake course registration.
     *
     * Eligibility:
     * 1. Student.status = 'intake_course'
     * 2. Has AcademicRecord with is_passed = false, completion_status finalized (not in_progress),
     *    and override_pass = false (not overridden to pass)
     * 3. failure_reason routes to the course-retake lane (ACAD-RET-001 Slice 2): grade-only
     *    failures (`grade_failed`) are excluded because they go to exam resit (`thi lại`).
     *    Attendance/both/manual and legacy null failure_reason stay eligible (forward-only).
     * 4. No existing non-terminal course_retake_registrations for same student+unit+semester
     * 5. Open CourseOfferings are attached when available, but are not required at source creation.
     *
     * @param  array{
     *   campus_id?: int|null,
     *   semester_id?: int|null,
     *   search?: string|null,
     *   unit_id?: int|null,
     * }  $filters
     * @return Collection<int, array{
     *   student: Student,
     *   failed_records: Collection,
     *   available_offerings: Collection,
     * }>
     */
    public function handle(array $filters): Collection
    {
        $semesterId = $filters['semester_id'] ?? null;
        $campusId = $filters['campus_id'] ?? null;
        $search = $filters['search'] ?? null;
        $unitId = $filters['unit_id'] ?? null;

        // Get students with intake_course status who have failed academic records
        $query = Student::query()
            ->where('status', 'intake_course')
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                });
            })
            ->whereHas('academicRecords', function ($q) use ($unitId) {
                $q->where('is_passed', false)
                    ->where('completion_status', '!=', 'in_progress')
                    ->where(fn ($q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
                    ->where(fn ($q) => $this->scopeToRetakeLane($q));
                if ($unitId) {
                    $q->where('unit_id', $unitId);
                }
            });

        $students = $query->with([
            'campus',
            'program',
        ])->get();

        $results = collect();

        foreach ($students as $student) {
            // Unit IDs the student has already passed — exclude from retake eligibility
            $passedUnitIds = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where(function ($q) {
                    $q->where('is_passed', true)
                        ->orWhere('override_pass', true);
                })
                ->pluck('unit_id');

            // Get failed academic records for units in student's curriculum version
            // Uses is_passed + override_pass instead of completion_status for pass/fail logic
            $failedRecords = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where('is_passed', false)
                ->where('completion_status', '!=', 'in_progress')
                ->where(fn ($q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
                ->where(fn ($q) => $this->scopeToRetakeLane($q))
                ->whereNotIn('unit_id', $passedUnitIds)
                ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
                ->whereIn('unit_id', function ($subQuery) use ($student) {
                    $subQuery->select('unit_id')
                        ->from('curriculum_units')
                        ->where('curriculum_version_id', $student->curriculum_version_id);
                })
                ->with('unit')
                ->get();

            foreach ($failedRecords as $record) {
                // Check if there's already an active retake registration for this student+unit+semester
                $hasActiveRegistration = CourseRetakeRegistration::query()
                    ->where('student_id', $student->id)
                    ->where('unit_id', $record->unit_id)
                    ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                    ->nonTerminal()
                    ->exists();

                if ($hasActiveRegistration) {
                    continue;
                }

                // Find available course offerings for this unit.
                // Use unit_id directly — curriculum_unit_id may be NULL on legacy offerings.
                // Exclude only cancelled; completed offerings with enrollment_status=open are valid for retake.
                $offeringsQuery = CourseOffering::query()
                    ->where('is_active', true)
                    ->where('enrollment_status', 'open')
                    ->whereNotIn('course_status', ['completed', 'cancelled'])
                    ->where('unit_id', $record->unit_id)
                    ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                    ->when($campusId, fn ($q) => $q->where('campus_id', $campusId));

                $offerings = $offeringsQuery->with(['semester', 'campus', 'lecture'])->get();

                $results->push([
                    'student' => $student,
                    'failed_record' => $record,
                    'unit' => $record->unit,
                    'available_offerings' => $offerings,
                ]);
            }
        }

        return $results;
    }

    /**
     * Constrain an AcademicRecord query to failures that belong to the course-retake
     * lane. Grade-only failures (`grade_failed`) route to exam resit and are excluded;
     * attendance/both/manual failures and legacy un-backfilled records (null) remain
     * eligible. Mirrors, in reverse, the exam-resit gate in CreateExamResitAttemptAction.
     *
     * @param  Builder<AcademicRecord>  $query
     */
    private function scopeToRetakeLane($query): void
    {
        $query->where('failure_reason', '!=', AcademicRecord::FAILURE_GRADE_FAILED)
            ->orWhereNull('failure_reason');
    }
}
