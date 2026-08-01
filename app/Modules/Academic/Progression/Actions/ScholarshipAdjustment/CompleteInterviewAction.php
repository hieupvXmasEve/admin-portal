<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipAdjustmentInterviewGuard;

final class CompleteInterviewAction
{
    /**
     * Complete the interview with minutes. The dossier becomes decidable.
     */
    public static function run(ScholarshipAdjustmentDossier $dossier, string $minutes, array $participants = []): ScholarshipAdjustmentDossier
    {
        ScholarshipAdjustmentInterviewGuard::assertPreDecision($dossier);

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED,
            'minutes' => $minutes,
            'minutes_version' => $dossier->minutes_version + 1,
            'interview_participants' => $participants,
        ]);

        return $dossier->refresh();
    }
}
