<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;

/**
 * Staff confirmation on the student's behalf — for students without a portal
 * account or who did not respond. Requires a mandatory contact-evidence note
 * (validated at the transport layer) and the confirm_on_behalf permission.
 * Flags confirmed_on_behalf = true so the acknowledgement is never mistaken
 * for a first-party student confirmation. One of the confirmation-column
 * single writers.
 */
final class RecordOnBehalfConfirmationAction
{
    public static function run(
        ScholarshipAdjustmentDossier $dossier,
        int $staffUserId,
        string $onBehalfNote,
    ): ScholarshipAdjustmentDossier {
        $confirmable = [
            ScholarshipAdjustmentDossier::CONFIRMATION_PENDING,
            ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE,
            ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED,
        ];

        if (! in_array($dossier->confirmation_status, $confirmable, true)) {
            throw new \DomainException(
                "Cannot confirm on behalf (confirmation_status: {$dossier->confirmation_status}).",
            );
        }

        $dossier->update([
            'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_minutes_version' => (int) $dossier->minutes_version,
            'confirmed_by_user_id' => $staffUserId,
            'confirmed_on_behalf' => true,
            'on_behalf_note' => $onBehalfNote,
        ]);

        return $dossier->refresh();
    }
}
