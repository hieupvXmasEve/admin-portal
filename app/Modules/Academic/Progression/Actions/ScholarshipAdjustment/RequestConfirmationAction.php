<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;

/**
 * Open the student-confirmation window on a dossier whose interview is done.
 * Sets confirmation_status = pending and stamps the deadline clock; a fresh
 * request clears any prior confirmed fields (e.g. re-request after a dispute).
 * One of the confirmation-column single writers.
 */
final class RequestConfirmationAction
{
    public static function run(ScholarshipAdjustmentDossier $dossier): ScholarshipAdjustmentDossier
    {
        if ($dossier->interview_status !== ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED) {
            throw new \DomainException('Interview must be completed before requesting student confirmation.');
        }

        $dossier->update([
            'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_PENDING,
            'confirmation_requested_at' => now(),
            'confirmed_at' => null,
            'confirmed_minutes_version' => null,
            'confirmed_by_user_id' => null,
            'confirmed_on_behalf' => false,
            'student_comment' => null,
            'on_behalf_note' => null,
        ]);

        return $dossier->refresh();
    }
}
