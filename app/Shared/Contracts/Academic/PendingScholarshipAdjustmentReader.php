<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

/**
 * Whether a student has an in-flight scholarship-adjustment dossier for a
 * target semester. Finance (batch-studio generate + preview) consumes this
 * through the Shared contract only — never
 * App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier
 * directly (ADR-0026 boundary).
 */
interface PendingScholarshipAdjustmentReader
{
    /**
     * @param  int[]  $studentIds
     * @return array<int,bool> studentId => hasInFlightDossier(targetSemesterId); every input id is present
     */
    public function inFlightByStudent(array $studentIds, int $targetSemesterId): array;
}
