<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Academic\Delivery\Actions\CreateExamResitAttemptAction;
use App\Modules\Academic\Delivery\Support\NonCancelledRetakeRegistration;
use App\Modules\Academic\Delivery\Support\OccupiedExamResitAttempt;
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
 * Cross-lane: a non-cancelled course-retake (including enrolled) hides thi lại.
 * Unfinished thi lại hides học lại. Otherwise both lists may show the same record.
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

            $attemptsByRecord = ExamResitAttempt::query()
                ->where('student_id', $student->id)
                ->get(['academic_record_id', 'status', 'attempt_number']);

            $activeRetakeRecordIds = NonCancelledRetakeRegistration::constrain(
                CourseRetakeRegistration::query()->where('student_id', $student->id)
            )->pluck('original_academic_record_id');

            $records = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where(fn (Builder $q) => $this->scopeEligibleRecords($q, $unitId, $semesterId))
                ->whereNotIn('unit_id', $passedUnitIds)
                ->whereNotIn('id', $activeRetakeRecordIds)
                ->with(['unit', 'courseOffering.syllabusTemplate'])
                ->get()
                ->reject(function (AcademicRecord $record) use ($attemptsByRecord): bool {
                    // Single consumed definition: attempt_number IS NOT NULL.
                    // A record is blocked while a resit source is in flight or
                    // when consumed attempts reached the live syllabus policy.
                    $attempts = $attemptsByRecord->where('academic_record_id', $record->id);
                    if ($attempts->contains(fn ($a) => in_array($a->status, OccupiedExamResitAttempt::STATUSES, true))) {
                        return true;
                    }

                    $consumed = $attempts->filter(fn ($a) => $a->attempt_number !== null)->count();

                    return $consumed > 0
                        && $consumed >= CreateExamResitAttemptAction::liveMaxAttemptsFor($record);
                })
                ->values();

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
