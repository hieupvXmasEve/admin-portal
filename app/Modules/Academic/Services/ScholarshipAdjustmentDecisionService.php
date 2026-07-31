<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Models\ScholarshipDefinition;
use App\Models\StudentScholarshipAward;
use App\Modules\Academic\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ONLY writer of scholarship_adjustment_dossiers.status. Every other service
 * (interview, confirmation handler, overdue command, restoration in Phase 5)
 * calls into this service instead of touching the column directly.
 *
 * Boundary rule: this class may depend on the Shared Finance contracts
 * namespace only — never a concrete Finance module class or model. See
 * AcademicFinanceBoundaryArchRedProofTest for the enforced regex.
 */
class ScholarshipAdjustmentDecisionService
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

    public function __construct(
        private readonly ScholarshipAdjustmentContract $financeContract,
        private readonly CampusPermissionReader $permissions,
    ) {}

    /**
     * Maker records a proposed decision. Blocked until the interview is
     * completed, EXCEPT the exception path (manual override reason + the
     * maker holds approve_scholarship_adjustment — the checker permission
     * doubles as the exception-override permission per the plan).
     */
    public function decide(
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

    /**
     * Checker approves. Maker != checker is enforced HERE (service-level),
     * not merely UI-level. Approved decisions are immutable afterward —
     * corrections are reversal decisions (Phase 5 reads this dossier back).
     */
    public function approve(ScholarshipAdjustmentDossier $dossier, int $checkerUserId): ScholarshipAdjustmentDossier
    {
        // lockForUpdate + transaction: two concurrent checkers must not both
        // pass the ready_for_decision check and both write approved_by_user_id.
        DB::transaction(function () use ($dossier, $checkerUserId): void {
            $locked = ScholarshipAdjustmentDossier::query()
                ->whereKey($dossier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION) {
                throw new \DomainException('Dossier is not awaiting approval.');
            }

            if ($locked->proposed_by_user_id === $checkerUserId) {
                throw new \DomainException('Maker and checker must differ.');
            }

            $checkerCodes = $this->permissions->permissionCodesForUserId($checkerUserId, (int) $locked->campus_id);

            if (! in_array('approve_scholarship_adjustment', $checkerCodes, true)) {
                throw new \DomainException("Checker lacks approve_scholarship_adjustment at campus {$locked->campus_id}.");
            }

            $locked->update([
                'status' => ScholarshipAdjustmentDossier::STATUS_APPROVED,
                'approved_by_user_id' => $checkerUserId,
                'approved_at' => now(),
            ]);
        });
        $dossier->refresh();

        if (! in_array($dossier->decision_type, ScholarshipAdjustmentDossier::MONEY_DECISION_TYPES, true)) {
            $finalStatus = $dossier->decision_type === ScholarshipAdjustmentDossier::DECISION_CANCEL
                ? ScholarshipAdjustmentDossier::STATUS_CANCELLED
                : ScholarshipAdjustmentDossier::STATUS_NO_ADJUSTMENT;

            $dossier->update(['status' => $finalStatus]);

            return $dossier->refresh();
        }

        return $this->handOffToFinance($dossier);
    }

    /**
     * Finance FIRST: the contract call never rolls back on a ledger refusal
     * (P2 handles that internally). The dossier status is written FROM the
     * returned outcome — an approval can never be silently undone by a
     * downstream refusal.
     */
    private function handOffToFinance(ScholarshipAdjustmentDossier $dossier): ScholarshipAdjustmentDossier
    {
        $award = StudentScholarshipAward::query()
            ->where('student_id', $dossier->student_id)
            ->with('scholarshipDefinition')
            ->first();

        if ($award === null || $award->scholarshipDefinition === null) {
            // Approved but Finance has nothing to apply against — route to
            // review rather than 404ing on an already-committed approval.
            $dossier->update(['status' => ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED]);
            Log::warning('Scholarship adjustment approved with no resolvable award', ['dossier_id' => $dossier->id]);

            return $dossier->refresh();
        }

        /** @var ScholarshipDefinition $definition */
        $definition = $award->scholarshipDefinition;

        $fingerprint = ScholarshipAdjustmentData::fingerprint(
            (string) $award->scholarship_code,
            (string) $definition->type,
            (string) $definition->amount,
        );

        $adjustedValue = $dossier->decision_type === ScholarshipAdjustmentDossier::DECISION_SUSPEND_FULL
            ? 0.0
            : (float) $dossier->decision_adjusted_amount;

        $result = $this->financeContract->apply(new ScholarshipAdjustmentData(
            student_id: (int) $dossier->student_id,
            source_semester_id: (int) $dossier->source_semester_id,
            target_semester_id: (int) $dossier->target_semester_id,
            adjusted_amount: $adjustedValue,
            reason: (string) $dossier->decision_reason,
            academic_dossier_id: (int) $dossier->id,
            maker_user_id: (int) $dossier->proposed_by_user_id,
            checker_user_id: (int) $dossier->approved_by_user_id,
            award_fingerprint: $fingerprint,
        ));

        if (! $result->accepted) {
            // Finance rejected the request outright (e.g. duplicate, bounds,
            // fingerprint mismatch) — the dossier stays approved but unresolved;
            // it never reverts to a pre-approval status, so the maker/checker
            // decision is preserved for audit and manual follow-up. The reason
            // is logged and appended to decision_reason so an operator sees
            // WHY, not just that review is required.
            Log::warning('Scholarship adjustment rejected by Finance', [
                'dossier_id' => $dossier->id,
                'reason_code' => $result->status,
                'message' => $result->message,
            ]);

            $dossier->update([
                'status' => ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED,
                'decision_reason' => $dossier->decision_reason." [Finance: {$result->status} — {$result->message}]",
            ]);

            return $dossier->refresh();
        }

        // Finance's own status is the source of truth: pending_apply (no
        // invoice yet) and applied are both legitimate "accepted" outcomes,
        // but only 'applied' means money actually moved — never mislabel a
        // pending write as applied.
        $mappedStatus = match ($result->status) {
            'applied' => ScholarshipAdjustmentDossier::STATUS_APPLIED,
            ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED => ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED,
            default => ScholarshipAdjustmentDossier::STATUS_APPROVED,
        };

        $dossier->update(['status' => $mappedStatus]);

        return $dossier->refresh();
    }
}
