<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Exceptions\StaleMinutesVersionException;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;

/**
 * Record the student's own portal response to the interview minutes.
 *
 * The response binds to an EXACT minutes_version: a mismatch means the minutes
 * were edited since the student fetched them → StaleMinutesVersionException
 * (HTTP 409). Ownership (this student owns this dossier) is enforced at the
 * transport layer from the authenticated student id, never request input.
 * One of the confirmation-column single writers.
 */
final class RecordStudentResponseAction
{
    public static function run(
        ScholarshipAdjustmentDossier $dossier,
        int $minutesVersion,
        bool $agree,
        ?string $comment = null,
        ?int $confirmedByUserId = null,
    ): ScholarshipAdjustmentDossier {
        $respondable = [
            ScholarshipAdjustmentDossier::CONFIRMATION_PENDING,
            ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE,
        ];

        if (! in_array($dossier->confirmation_status, $respondable, true)) {
            throw new \DomainException(
                "No open confirmation to respond to (confirmation_status: {$dossier->confirmation_status}).",
            );
        }

        if ($minutesVersion !== (int) $dossier->minutes_version) {
            throw new StaleMinutesVersionException($minutesVersion, (int) $dossier->minutes_version);
        }

        if ($agree) {
            $dossier->update([
                'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_minutes_version' => $minutesVersion,
                'confirmed_by_user_id' => $confirmedByUserId,
                'confirmed_on_behalf' => false,
                'student_comment' => $comment,
            ]);

            return $dossier->refresh();
        }

        $dossier->update([
            'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED,
            'confirmed_at' => null,
            'confirmed_minutes_version' => null,
            'confirmed_by_user_id' => null,
            'student_comment' => $comment,
        ]);

        return $dossier->refresh();
    }
}
