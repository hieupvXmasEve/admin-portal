<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Academic\ScholarshipDossierCloser;

/**
 * Single writer for STATUS_CLOSED (Phase 5). Called by Finance's restoration
 * approval action through the Shared contract once a restoration proposal is
 * approved. No-op when the dossier does not exist or is already terminal —
 * an approval must never crash on a dossier that was already closed/cancelled
 * by an unrelated path.
 */
class CloseDossierAction implements ScholarshipDossierCloser
{
    /** Terminal statuses that must never be reopened by a close call. */
    private const TERMINAL_STATUSES = [
        ScholarshipAdjustmentDossier::STATUS_CLOSED,
        ScholarshipAdjustmentDossier::STATUS_CANCELLED,
        ScholarshipAdjustmentDossier::STATUS_NO_ADJUSTMENT,
        ScholarshipAdjustmentDossier::STATUS_NOT_APPLICABLE,
    ];

    public function closeDossier(int $dossierId): void
    {
        $dossier = ScholarshipAdjustmentDossier::query()->find($dossierId);

        if ($dossier === null || in_array($dossier->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $dossier->update(['status' => ScholarshipAdjustmentDossier::STATUS_CLOSED]);
    }
}
