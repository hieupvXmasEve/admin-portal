<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;

/**
 * Mark a single dossier's pending confirmation as overdue. Called by the daily
 * command for dossiers whose confirmation was requested more than one calendar
 * day ago.
 *
 * RACE GUARD: refuses when confirmed_at is already set — a student who confirms
 * seconds before the nightly run must never be flipped to overdue. Only a
 * still-pending confirmation transitions.
 */
final class MarkOverdueAction
{
    public static function run(ScholarshipAdjustmentDossier $dossier): ScholarshipAdjustmentDossier
    {
        // Atomic conditional transition closes the TOCTOU window: the command
        // loads rows then calls this per-row, so a student confirming between
        // the load and here must not be flipped to overdue. The WHERE re-asserts
        // "still pending, not yet confirmed" at write time, so a concurrent
        // confirm (which sets confirmed_at + status=confirmed) makes this a
        // zero-row no-op instead of clobbering a valid acknowledgement.
        ScholarshipAdjustmentDossier::query()
            ->whereKey($dossier->id)
            ->where('confirmation_status', ScholarshipAdjustmentDossier::CONFIRMATION_PENDING)
            ->whereNull('confirmed_at')
            ->update(['confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE]);

        return $dossier->refresh();
    }
}
