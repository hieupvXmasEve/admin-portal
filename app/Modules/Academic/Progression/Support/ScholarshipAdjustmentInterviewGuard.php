<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;

/**
 * Shared guard logic for the interview-stage actions — moved verbatim out of
 * the former interview service class (pre-Progression refactor).
 */
class ScholarshipAdjustmentInterviewGuard
{
    /**
     * Once a dossier leaves the pre-decision lifecycle, no interview action
     * may reopen it — that would let interview permissions alone undo an
     * approved/applied money decision.
     */
    private const PRE_DECISION_STATUSES = [
        ScholarshipAdjustmentDossier::STATUS_IDENTIFIED,
        ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
        ScholarshipAdjustmentDossier::STATUS_STUDENT_NO_SHOW,
    ];

    public static function assertPreDecision(ScholarshipAdjustmentDossier $dossier): void
    {
        if (! in_array($dossier->status, self::PRE_DECISION_STATUSES, true)) {
            throw new \DomainException("Dossier is past the interview stage (status: {$dossier->status}) — interview actions are no longer permitted.");
        }
    }

    /**
     * Edit minutes after completion (correction) — bumps the version so a
     * student who already confirmed an earlier version is invalidated. Allowed
     * up through the decision stage (interviewed/ready_for_decision); blocked
     * once approved/applied — a decided dossier's evidence is frozen.
     */
    public static function assertMinutesEditable(ScholarshipAdjustmentDossier $dossier): void
    {
        $editableStatuses = [
            ...self::PRE_DECISION_STATUSES,
            ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
            ScholarshipAdjustmentDossier::STATUS_AWAITING_STUDENT_CONFIRMATION,
            ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION,
        ];

        if (! in_array($dossier->status, $editableStatuses, true)) {
            throw new \DomainException("Dossier is past the decision stage (status: {$dossier->status}) — minutes are frozen.");
        }
    }
}
