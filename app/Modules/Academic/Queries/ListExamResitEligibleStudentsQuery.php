<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Academic\Actions\CreateExamResitAttemptAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eligible students/records for exam-resit (thi lại) registration (ACAD-RET-001 slice 7).
 *
 * The exam-resit lane is the inverse of {@see ListRetakeCourseEligibleStudentsQuery}:
 * only GRADE-only failures (`failure_reason = grade_failed`) qualify. Attendance/both
 * failures route to course retake, and `not_recorded` attendance blocks eligibility
 * until resolved. Unlike the retake list, no curriculum-membership join is needed: a
 * finalized failed academic record already proves the student took the unit.
 *
 * Mirrors the gate in {@see CreateExamResitAttemptAction}.
 */
class ListExamResitEligibleStudentsQuery
{
    /**
     * Statuses that mean an exam-resit source is already in flight or consumed for
     * a record, so it must not be offered again.
     */
    private const BLOCKING_ATTEMPT_STATUSES = [
        ExamResitAttempt::STATUS_REQUESTED,
        ExamResitAttempt::STATUS_APPROVED,
        ExamResitAttempt::STATUS_SCHEDULED,
        ExamResitAttempt::STATUS_COMPLETED,
    ];

    /**
     * @param  array{campus_id?:int|null,semester_id?:int|null,search?:string|null,unit_id?:int|null}  $filters
     * @return Collection<int,array{student:Student,failed_record:AcademicRecord,unit:mixed}>
     */
    public function handle(array $filters): Collection
    {
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
            ->whereHas('academicRecords', fn (Builder $q) => $this->scopeEligibleRecords($q, $unitId))
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
                ->whereIn('status', self::BLOCKING_ATTEMPT_STATUSES)
                ->pluck('academic_record_id');

            $records = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where(fn (Builder $q) => $this->scopeEligibleRecords($q, $unitId))
                ->whereNotIn('unit_id', $passedUnitIds)
                ->whereNotIn('id', $blockedRecordIds)
                ->with('unit')
                ->get()
                ->filter(fn (AcademicRecord $record): bool => $this->attendanceIsRecorded($record));

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
    private function scopeEligibleRecords(Builder $query, ?int $unitId): void
    {
        $query->where('is_passed', false)
            ->where('completion_status', '!=', 'in_progress')
            ->where('grade_status', 'final')
            ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
            ->where('failure_reason', AcademicRecord::FAILURE_GRADE_FAILED)
            ->where(fn (Builder $q) => $q->whereNull('total_not_recorded')->orWhere('total_not_recorded', 0))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('unit_id', $id));
    }

    private function attendanceIsRecorded(AcademicRecord $record): bool
    {
        $snapshot = $record->failure_reason_snapshot ?? [];

        return ($snapshot['attendance_evidence_state'] ?? null) !== 'not_recorded'
            && (int) $record->total_not_recorded === 0;
    }
}
