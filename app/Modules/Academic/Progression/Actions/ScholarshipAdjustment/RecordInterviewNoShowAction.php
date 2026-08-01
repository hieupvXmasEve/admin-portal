<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipAdjustmentInterviewGuard;

final class RecordInterviewNoShowAction
{
    public static function run(ScholarshipAdjustmentDossier $dossier): ScholarshipAdjustmentDossier
    {
        ScholarshipAdjustmentInterviewGuard::assertPreDecision($dossier);

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_STUDENT_NO_SHOW,
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_STUDENT_NO_SHOW,
        ]);

        return $dossier->refresh();
    }
}
