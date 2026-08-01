<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipAdjustmentInterviewGuard;

final class EditMinutesAction
{
    public static function run(ScholarshipAdjustmentDossier $dossier, string $minutes): ScholarshipAdjustmentDossier
    {
        ScholarshipAdjustmentInterviewGuard::assertMinutesEditable($dossier);

        $dossier->update([
            'minutes' => $minutes,
            'minutes_version' => $dossier->minutes_version + 1,
        ]);

        return $dossier->refresh();
    }
}
