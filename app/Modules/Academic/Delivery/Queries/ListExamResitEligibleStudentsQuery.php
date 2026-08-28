<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Academic\Delivery\Actions\CreateExamResitAttemptAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eligible students/records for exam-resit (thi lại) registration (ACAD-RET-001 slice 7).
 *
 * Any finalized failed academic record qualifies, regardless of `failure_reason`
 * (grade/attendance/both/manual) — staff reviews the failure reason shown on the
 * create page and decides who to register. Only true duplicates are excluded: a
 * unit already passed via another record, or a record with an in-flight/consumed
 * exam-resit attempt (see {@see ListExamResitBlockedStudentsQuery} for that set).
 * No curriculum-membership join is needed: a finalized failed academic record
 * already proves the student took the unit.
 *
 * Cross-lane guard: a record with an active (non-terminal) course-retake
 * registration is excluded too — it's already being handled in the retake lane,
 * see {@see \App\Modules\Academic\Delivery\Queries\ListRetakeCourseEligibleStudentsQuery}.
 *
 * Mirrors the gate in {@see CreateExamResitAttemptAction}.
 */
class ListExamResitEligibleStudentsQuery
{
    /**
     * @param  array{campus_id?:int|null,semester_id?:int|null,search?:string|null,unit_id?:int|null}  $filters
     * @return Collection<int,array{student:Student,failed_record:AcademicRecord,unit:mixed}>
     */
    public function handle(array $filters): Collection
    {
        $campusId = $filters['campus_id'] ?? null;
        $semesterId = $filters['semester_id'] ?? null;
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
            ->whereHas('academicRecords', fn (Builder $q) => $this->scopeEligibleRecords($q, $unitId, $semesterId))
            ->with(['campus', 'program'])
            ->get();

        $results = collect();

        foreach ($students as $student) {
            $passedUnitIds = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where(fn (Builder $q) => $q->where('is_passed', true)->orWhere('override_pass', true))
                ->pluck('unit_id');

            $blockedRecordIds = ExamResitAttempt::query()
                ->where('student_id', $student->id)
                ->whereIn('status', ExamResitAttempt::IN_FLIGHT_OR_CONSUMED_STATUSES)
                ->pluck('academic_record_id');

            $activeRetakeUnitIds = CourseRetakeRegistration::query()
                ->where('student_id', $student->id)
                ->nonTerminal()
                ->pluck('unit_id');

            $records = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where(fn (Builder $q) => $this->scopeEligibleRecords($q, $unitId, $semesterId))
                ->whereNotIn('unit_id', $passedUnitIds)
                ->whereNotIn('id', $blockedRecordIds)
                ->whereNotIn('unit_id', $activeRetakeUnitIds)
                ->with('unit')
                ->get();

            foreach ($records as $record) {
                $results->push([
                    'student' => $student,
                    'failed_record' => $record,
                    'unit' => $record->unit,
                ]);
            }
        }

        return $results;
    }

    /**
     * @param  Builder<AcademicRecord>  $query
     */
    private function scopeEligibleRecords(Builder $query, ?int $unitId, ?int $semesterId): void
    {
        $query->where('is_passed', false)
            ->where('completion_status', '!=', 'in_progress')
            ->where('grade_status', 'final')
            ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('unit_id', $id))
            ->when($semesterId, fn (Builder $q, int $id) => $q->where('semester_id', $id));
    }
}
