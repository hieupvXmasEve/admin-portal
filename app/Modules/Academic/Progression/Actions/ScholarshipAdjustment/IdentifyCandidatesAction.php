<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Queries\ScholarshipAdjustmentCandidateQuery;
use App\Modules\Academic\Progression\Support\ScholarshipAdjustmentDossierFactory;

/**
 * Turns identified candidates into scholarship_adjustment_dossiers. One
 * dossier per student per (source, target) semester pair — a run is
 * idempotent: an existing non-cancelled dossier for the pair is left alone.
 */
class IdentifyCandidatesAction
{
    public function __construct(private readonly ScholarshipAdjustmentCandidateQuery $query) {}

    /**
     * @param  array<int, int>|null  $selectedStudentIds  When given, only these student IDs are turned into
     *                                                    dossiers (staff-picked subset from the preview screen).
     *                                                    Null keeps the original "create every candidate" behavior
     *                                                    (CLI / scheduler use).
     * @return array{created: int, skipped_existing: int, excluded_null_is_passed: int}
     */
    public function run(int $campusId, int $sourceSemesterId, int $targetSemesterId, int $actorUserId, ?array $selectedStudentIds = null): array
    {
        $result = $this->query->handle($campusId, $sourceSemesterId, $targetSemesterId);

        $candidates = $selectedStudentIds === null
            ? $result['candidates']
            : $result['candidates']->whereIn('student_id', $selectedStudentIds);

        $created = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
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

            app(ScholarshipAdjustmentDossierFactory::class)->create(
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
}
