<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\StudentScholarshipAward;
use Illuminate\Support\Collection;

/**
 * Dedicated query for Phase 3 candidate identification. Deliberately NOT a
 * reuse of App\Services\FailedStudentsService — that service reads
 * session('current_campus_id') internally (session is null under artisan, so
 * campus_id = NULL matches zero rows) and lacks the unit-type, finalization,
 * override, and appeal predicates this workflow needs.
 *
 * Every predicate takes explicit arguments; nothing is derived from session
 * or "previous semester" guessing (semesters.start_date is nullable, there is
 * no campus column on semesters, and is_active is not unique).
 */
class ScholarshipAdjustmentCandidateQuery
{
    /**
     * Cutoff for the is_passed gate fix (commit c5cb7b23, 2026-07-04). Records
     * finalized before this date may carry a stale is_passed value — the
     * candidate is still surfaced (no recalc), but flagged for mandatory
     * manual verification instead of silent trust.
     */
    private const IS_PASSED_GATE_FIX_DATE = '2026-07-04';

    /**
     * @return array{candidates: Collection<int, array{student_id: int, failed_records: Collection}>, excluded_null_is_passed: int}
     */
    public function handle(int $campusId, int $sourceSemesterId, int $targetSemesterId): array
    {
        $this->validateSemesterPair($sourceSemesterId, $targetSemesterId);

        $excludedNullIsPassed = AcademicRecord::query()
            ->where('campus_id', $campusId)
            ->where('semester_id', $sourceSemesterId)
            ->whereNull('is_passed')
            ->count();

        $failedRecords = AcademicRecord::query()
            ->where('academic_records.campus_id', $campusId)
            ->where('academic_records.semester_id', $sourceSemesterId)
            ->where('academic_records.is_passed', false)
            ->whereNotNull('academic_records.is_passed')
            ->whereNotNull('academic_records.grade_finalized_date')
            ->where('academic_records.override_pass', false)
            ->whereHas('unit', fn ($query) => $query->where('unit_type', '!=', 'egc'))
            ->whereHas('student', fn ($query) => $query->where('status', 'intake_course'))
            ->with(['unit', 'student'])
            ->get();

        // Component-level open-appeal exclusion — no record-level appeal
        // entity exists. Spelled out explicitly per the plan. Scoped to the
        // course offerings actually in play this run — an unscoped scan would
        // load every pending appeal campus-wide into memory.
        $candidateOfferingIds = $failedRecords->pluck('course_offering_id')->unique()->values();

        $appealedStudentCourseKeys = AssessmentComponentDetailScore::query()
            ->where('appeal_status', 'pending')
            ->whereIn('course_offering_id', $candidateOfferingIds)
            ->get(['student_id', 'course_offering_id'])
            ->map(fn ($row) => $row->student_id.':'.$row->course_offering_id)
            ->flip();

        $failedRecords = $failedRecords->reject(
            fn (AcademicRecord $record) => $appealedStudentCourseKeys
                ->has($record->student_id.':'.$record->course_offering_id)
        );

        // Active scholarship award — no valid_from/valid_until window check:
        // a scholarship award runs for the whole program, not a single
        // semester, so the target semester's start date is not a validity
        // boundary.
        $studentIdsWithFailure = $failedRecords->pluck('student_id')->unique()->values();

        $activeAwardStudentIds = StudentScholarshipAward::query()
            ->whereIn('student_id', $studentIdsWithFailure)
            ->whereHas('scholarshipDefinition', fn ($query) => $query->where('is_active', true))
            ->pluck('student_id')
            ->unique();

        // Continuation: an active/completed registration in the target semester.
        $continuingStudentIds = CourseRegistration::query()
            ->whereIn('student_id', $activeAwardStudentIds)
            ->where('semester_id', $targetSemesterId)
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->unique();

        $eligibleFailures = $failedRecords->filter(
            fn (AcademicRecord $record) => $continuingStudentIds->contains($record->student_id)
        );

        $candidates = $eligibleFailures
            ->groupBy('student_id')
            ->map(fn (Collection $records, int $studentId) => [
                'student_id' => $studentId,
                'failed_records' => $records,
            ])
            ->values();

        return [
            'candidates' => $candidates,
            'excluded_null_is_passed' => $excludedNullIsPassed,
        ];
    }

    /**
     * Both semesters must exist, be non-archived, carry a start_date, and be
     * ordered source < target. No auto-derivation of "previous semester".
     */
    private function validateSemesterPair(int $sourceSemesterId, int $targetSemesterId): void
    {
        $semesters = Semester::query()
            ->whereIn('id', [$sourceSemesterId, $targetSemesterId])
            ->get()
            ->keyBy('id');

        $source = $semesters->get($sourceSemesterId);
        $target = $semesters->get($targetSemesterId);

        if ($source === null || $target === null) {
            throw new \InvalidArgumentException('Source or target semester does not exist.');
        }

        if ($source->is_archived || $target->is_archived) {
            throw new \InvalidArgumentException('Source and target semesters must not be archived.');
        }

        if ($source->start_date === null || $target->start_date === null) {
            throw new \InvalidArgumentException('Source and target semesters must have a start_date.');
        }

        if (! $source->start_date->lessThan($target->start_date)) {
            throw new \InvalidArgumentException('Source semester must start before target semester.');
        }
    }

    /** Whether a record predates the is_passed gate fix and needs manual review. */
    public function needsDataReview(Collection $records): bool
    {
        return $records->contains(
            fn (AcademicRecord $record) => $record->grade_finalized_date !== null
                && $record->grade_finalized_date->lt(self::IS_PASSED_GATE_FIX_DATE)
        );
    }
}
