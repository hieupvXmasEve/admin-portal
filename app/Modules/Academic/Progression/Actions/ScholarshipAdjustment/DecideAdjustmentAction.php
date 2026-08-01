<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * Maker records a proposed decision on a dossier.
 *
 * Boundary rule: this class may depend on the Shared Finance contracts
 * namespace only — never a concrete Finance module class or model. See
 * AcademicFinanceBoundaryArchRedProofTest for the enforced regex.
 */
class DecideAdjustmentAction
{
    /** Statuses that already resolved a decision — decide() is refused once here. */
    private const TERMINAL_DECISION_STATUSES = [
        ScholarshipAdjustmentDossier::STATUS_APPROVED,
        ScholarshipAdjustmentDossier::STATUS_APPLIED,
        ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED,
        ScholarshipAdjustmentDossier::STATUS_NO_ADJUSTMENT,
        ScholarshipAdjustmentDossier::STATUS_CANCELLED,
        ScholarshipAdjustmentDossier::STATUS_CLOSED,
    ];

    public function __construct(private readonly CampusPermissionReader $permissions) {}

    /**
     * Maker records a proposed decision. Blocked until the interview is
     * completed, EXCEPT the exception path (manual override reason + the
     * maker holds approve_scholarship_adjustment — the checker permission
     * doubles as the exception-override permission per the plan).
     */
    public function run(
        ScholarshipAdjustmentDossier $dossier,
        string $decisionType,
        ?float $adjustedAmount,
        string $reason,
        int $makerUserId,
        ?string $exceptionOverrideReason = null,
    ): ScholarshipAdjustmentDossier {
        if (! in_array($decisionType, ScholarshipAdjustmentDossier::DECISION_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown decision type: {$decisionType}");
        }

        // 'defer' has no terminal resolution defined yet (Phase 4/5 concern) —
        // refuse rather than silently closing it out as no_adjustment.
        if ($decisionType === ScholarshipAdjustmentDossier::DECISION_DEFER) {
            throw new \InvalidArgumentException('Defer is not yet a supported terminal decision.');
        }

        if (in_array($dossier->status, self::TERMINAL_DECISION_STATUSES, true)) {
            throw new \DomainException("Dossier already resolved (status: {$dossier->status}) — decisions are immutable once approved.");
        }

        if (! $dossier->isDecidable()) {
            if ($exceptionOverrideReason === null) {
                throw new \DomainException('Interview must be completed before a decision, or provide an exception reason.');
            }

            $makerCodes = $this->permissions->permissionCodesForUserId($makerUserId, (int) $dossier->campus_id);

            if (! in_array('approve_scholarship_adjustment', $makerCodes, true)) {
                throw new \DomainException('Exception path requires approve_scholarship_adjustment at the dossier campus.');
            }
        }

        if (in_array($decisionType, ScholarshipAdjustmentDossier::MONEY_DECISION_TYPES, true) && $adjustedAmount === null) {
            throw new \InvalidArgumentException('adjusted_amount is required for reduce/suspend_full decisions.');
        }

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION,
            'decision_type' => $decisionType,
            'decision_adjusted_amount' => $adjustedAmount,
            'decision_reason' => $reason.($exceptionOverrideReason !== null ? " [Exception: {$exceptionOverrideReason}]" : ''),
            'proposed_by_user_id' => $makerUserId,
            'decided_at' => now(),
        ]);

        return $dossier->refresh();
    }
}
