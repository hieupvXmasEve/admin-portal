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

        $update = [
            'minutes' => $minutes,
            'minutes_version' => $dossier->minutes_version + 1,
        ];

        // Editing the minutes invalidates any confirmation the student gave
        // against the OLD version — reset to pending and restart the clock so
        // the student re-acknowledges the corrected minutes (P4). Only touch
        // confirmation fields if a confirmation was ever requested.
        if ($dossier->confirmation_status !== null) {
            $update += [
                'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_PENDING,
                'confirmation_requested_at' => now(),
                'confirmed_at' => null,
                'confirmed_minutes_version' => null,
                'confirmed_by_user_id' => null,
                'confirmed_on_behalf' => false,
                'student_comment' => null,
                'on_behalf_note' => null,
            ];
        }

        $dossier->update($update);

        return $dossier->refresh();
    }
}
