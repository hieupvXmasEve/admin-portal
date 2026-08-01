<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Models\AcademicRecord;
use App\Models\Student;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Queries\ScholarshipAdjustmentCandidateQuery;
use App\Modules\Academic\Progression\Support\ScholarshipAdjustmentDossierFactory;

class AddCandidateManuallyAction
{
    public function __construct(private readonly ScholarshipAdjustmentCandidateQuery $query) {}

    /**
     * Manual add by MSSV — bypasses the auto criteria but requires a written
     * exception reason (route-gated on manage_scholarship_adjustment_candidate).
     */
    public function run(
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

        return app(ScholarshipAdjustmentDossierFactory::class)->create(
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
}
