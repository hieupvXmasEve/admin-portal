<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Models\AcademicRecord;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Academic\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Queries\ScholarshipAdjustmentCandidateQuery;
use Illuminate\Support\Collection;

/**
 * Turns identified/manual candidates into scholarship_adjustment_dossiers.
 * One dossier per student per (source, target) semester pair — a run is
 * idempotent: an existing non-cancelled dossier for the pair is left alone.
 */
class ScholarshipAdjustmentCandidateService
{
    public function __construct(private readonly ScholarshipAdjustmentCandidateQuery $query) {}

    /**
     * @return array{created: int, skipped_existing: int, excluded_null_is_passed: int}
     */
    public function identify(int $campusId, int $sourceSemesterId, int $targetSemesterId, int $actorUserId): array
    {
        $result = $this->query->handle($campusId, $sourceSemesterId, $targetSemesterId);

        $created = 0;
        $skipped = 0;

        foreach ($result['candidates'] as $candidate) {
            $studentId = $candidate['student_id'];

            $exists = ScholarshipAdjustmentDossier::query()
                ->where('student_id', $studentId)
                ->where('source_semester_id', $sourceSemesterId)
                ->where('target_semester_id', $targetSemesterId)
                ->where('status', '!=', ScholarshipAdjustmentDossier::STATUS_CANCELLED)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $this->createDossier(
                $studentId,
                $campusId,
                $sourceSemesterId,
                $targetSemesterId,
                $candidate['failed_records'],
                ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
                null,
                $actorUserId,
            );
            $created++;
        }

        return [
            'created' => $created,
            'skipped_existing' => $skipped,
            'excluded_null_is_passed' => $result['excluded_null_is_passed'],
        ];
    }

    /**
     * Manual add by MSSV — bypasses the auto criteria but requires a written
     * exception reason (route-gated on manage_scholarship_adjustment_candidate).
     */
    public function addManually(
        string $studentCode,
        int $sourceSemesterId,
        int $targetSemesterId,
        string $exceptionReason,
        int $actorUserId,
    ): ScholarshipAdjustmentDossier {
        $student = Student::query()->where('student_id', $studentCode)->first();

        if ($student === null) {
            throw new \DomainException("Student {$studentCode} does not exist.");
        }

        $exists = ScholarshipAdjustmentDossier::query()
            ->where('student_id', $student->id)
            ->where('source_semester_id', $sourceSemesterId)
            ->where('target_semester_id', $targetSemesterId)
            ->where('status', '!=', ScholarshipAdjustmentDossier::STATUS_CANCELLED)
            ->exists();

        if ($exists) {
            throw new \DomainException('An active dossier already exists for this student and semester pair.');
        }

        // Manual add bypasses the AUTO CRITERIA (EGC/override/appeal/award
        // validity/continuation) per the plan's exception path — but the
        // evidence list must still mean actually-failed courses, not every
        // record, or the dossier misrepresents its own evidence.
        $failedRecords = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $sourceSemesterId)
            ->where('is_passed', false)
            ->get();

        return $this->createDossier(
            $student->id,
            (int) $student->campus_id,
            $sourceSemesterId,
            $targetSemesterId,
            $failedRecords,
            ScholarshipAdjustmentDossier::SOURCE_MANUAL,
            $exceptionReason,
            $actorUserId,
        );
    }

    /**
     * @param  Collection<int, AcademicRecord>  $failedRecords
     */
    private function createDossier(
        int $studentId,
        int $campusId,
        int $sourceSemesterId,
        int $targetSemesterId,
        Collection $failedRecords,
        string $source,
        ?string $exceptionReason,
        int $actorUserId,
    ): ScholarshipAdjustmentDossier {
        $award = StudentScholarshipAward::query()
            ->where('student_id', $studentId)
            ->with('scholarshipDefinition')
            ->first();

        if ($award === null || $award->scholarshipDefinition === null) {
            throw new \DomainException('Student has no active scholarship award — no dossier can be created.');
        }

        $definition = $award->scholarshipDefinition;

        $snapshot = $failedRecords->map(fn (AcademicRecord $record) => [
            'unit_code' => $record->unit?->code,
            'unit_name' => $record->unit?->name,
            'attempt_no' => $record->attempt_number,
            'academic_record_id' => $record->id,
            'grade_finalized_date' => $record->grade_finalized_date?->toDateString(),
        ])->values()->all();

        $needsDataReview = $this->query->needsDataReview($failedRecords);

        return ScholarshipAdjustmentDossier::create([
            'student_id' => $studentId,
            'campus_id' => $campusId,
            'source_semester_id' => $sourceSemesterId,
            'target_semester_id' => $targetSemesterId,
            'status' => ScholarshipAdjustmentDossier::STATUS_IDENTIFIED,
            'source' => $source,
            'manual_exception_reason' => $exceptionReason,
            'failed_courses_snapshot' => $snapshot,
            'original_scholarship_code' => $award->scholarship_code,
            'original_type' => $definition?->type,
            'original_amount' => $definition?->amount,
            'needs_data_review' => $needsDataReview,
            'created_by_user_id' => $actorUserId,
        ]);
    }
}
