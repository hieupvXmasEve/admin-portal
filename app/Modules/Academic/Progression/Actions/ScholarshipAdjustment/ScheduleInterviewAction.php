<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipAdjustmentInterviewGuard;

final class ScheduleInterviewAction
{
    public static function run(
        ScholarshipAdjustmentDossier $dossier,
        \DateTimeInterface $scheduledAt,
        string $mode,
        ?string $location,
        int $staffUserId,
    ): ScholarshipAdjustmentDossier {
        ScholarshipAdjustmentInterviewGuard::assertPreDecision($dossier);

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED,
            'interview_scheduled_at' => $scheduledAt,
            'interview_mode' => $mode,
            'interview_location' => $location,
            'interview_staff_id' => $staffUserId,
        ]);

        return $dossier->refresh();
    }
}
