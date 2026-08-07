<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader;

/**
 * Implementation for App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader.
 * Single set-based query — Finance calls this once per batch run, not per student.
 */
class PendingScholarshipAdjustmentQuery implements PendingScholarshipAdjustmentReader
{
    public function inFlightByStudent(array $studentIds, int $targetSemesterId): array
    {
        $map = array_fill_keys($studentIds, false);

        if ($studentIds === []) {
            return $map;
        }

        $inFlightIds = ScholarshipAdjustmentDossier::query()
            ->whereIn('student_id', $studentIds)
            ->where('target_semester_id', $targetSemesterId)
            ->whereIn('status', ScholarshipAdjustmentDossier::IN_FLIGHT_STATUSES)
            ->pluck('student_id');

        foreach ($inFlightIds as $studentId) {
            $map[$studentId] = true;
        }

        return $map;
    }
}
