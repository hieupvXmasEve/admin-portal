<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Duplicate/blocked records for the exam-resit (thi lại) create page — the
 * complement of {@see ListExamResitEligibleStudentsQuery}. A finalized failed
 * record is blocked only when it is a true duplicate, never on grade/attendance
 * grounds: the unit was already passed via another record, or the record already
 * has an in-flight/consumed exam-resit attempt. Shown to staff for transparency,
 * not selectable for registration.
 */
class ListExamResitBlockedStudentsQuery
{
    public const REASON_UNIT_ALREADY_PASSED = 'unit_already_passed';

    public const REASON_RESIT_ALREADY_IN_FLIGHT = 'resit_already_in_flight';

    private const REASON_LABELS = [
        self::REASON_UNIT_ALREADY_PASSED => 'Đã pass môn này ở bản ghi khác',
        self::REASON_RESIT_ALREADY_IN_FLIGHT => 'Đã có đăng ký thi lại đang xử lý/hoàn tất cho bản ghi này',
    ];

    /**
     * @param  array{campus_id?:int|null,semester_id?:int|null,search?:string|null,unit_id?:int|null}  $filters
     * @return Collection<int,array{student:Student,failed_record:AcademicRecord,unit:mixed,reason_code:string,reason_label:string}>
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
            ->whereHas('academicRecords', fn (Builder $q) => $this->scopeFailedRecords($q, $unitId, $semesterId))
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

            $records = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where(fn (Builder $q) => $this->scopeFailedRecords($q, $unitId, $semesterId))
                ->where(fn (Builder $q) => $q->whereIn('unit_id', $passedUnitIds)->orWhereIn('id', $blockedRecordIds))
                ->with('unit')
                ->get();

            foreach ($records as $record) {
                $reasonCode = $passedUnitIds->contains($record->unit_id)
                    ? self::REASON_UNIT_ALREADY_PASSED
                    : self::REASON_RESIT_ALREADY_IN_FLIGHT;

                $results->push([
                    'student' => $student,
                    'failed_record' => $record,
                    'unit' => $record->unit,
                    'reason_code' => $reasonCode,
                    'reason_label' => self::REASON_LABELS[$reasonCode],
                ]);
            }
        }

        return $results;
    }

    /**
     * @param  Builder<AcademicRecord>  $query
     */
    private function scopeFailedRecords(Builder $query, ?int $unitId, ?int $semesterId): void
    {
        $query->where('is_passed', false)
            ->where('completion_status', '!=', 'in_progress')
            ->where('grade_status', 'final')
            ->where(fn (Builder $q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('unit_id', $id))
            ->when($semesterId, fn (Builder $q, int $id) => $q->where('semester_id', $id));
    }
}
