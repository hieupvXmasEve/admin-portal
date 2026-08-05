<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\AcademicRecord;
use App\Models\StudentScholarshipAward;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Queries\ScholarshipAdjustmentCandidateQuery;
use Illuminate\Support\Collection;

/**
 * Shared dossier-creation logic used by both IdentifyCandidatesAction and
 * AddCandidateManuallyAction — one dossier per student per (source, target)
 * semester pair.
 */
class ScholarshipAdjustmentDossierFactory
{
    public function __construct(private readonly ScholarshipAdjustmentCandidateQuery $query) {}

    /**
     * @param  Collection<int, AcademicRecord>  $failedRecords
     */
    public function create(
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
            throw new \DomainException('Sinh viên này không có học bổng nên không có gì để xét.');
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
