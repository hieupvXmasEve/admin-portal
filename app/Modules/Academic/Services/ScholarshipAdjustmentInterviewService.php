<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Modules\Academic\Models\ScholarshipAdjustmentDossier;

/**
 * Interview lifecycle for a dossier: schedule, reschedule, record no-show,
 * complete with minutes. Minutes edits bump `minutes_version` so the student
 * portal confirmation (Phase 4) can detect a stale version.
 *
 * This service writes interview_* columns and the dossier `status` transition
 * that directly follows an interview event (identified→interview_scheduled,
 * interview_scheduled→interviewed/student_no_show). Decision-stage status
 * transitions belong exclusively to ScholarshipAdjustmentDecisionService.
 */
class ScholarshipAdjustmentInterviewService
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

    private function guardPreDecision(ScholarshipAdjustmentDossier $dossier): void
    {
        if (! in_array($dossier->status, self::PRE_DECISION_STATUSES, true)) {
            throw new \DomainException("Dossier is past the interview stage (status: {$dossier->status}) — interview actions are no longer permitted.");
        }
    }

    public function schedule(
        ScholarshipAdjustmentDossier $dossier,
        \DateTimeInterface $scheduledAt,
        string $mode,
        ?string $location,
        int $staffUserId,
    ): ScholarshipAdjustmentDossier {
        $this->guardPreDecision($dossier);

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED,
            'interview_scheduled_at' => $scheduledAt,
            'interview_mode' => $mode,
            'interview_location' => $location,
            'interview_staff_id' => $staffUserId,
        ]);

        return $dossier->refresh();
    }

    public function reschedule(ScholarshipAdjustmentDossier $dossier, \DateTimeInterface $scheduledAt): ScholarshipAdjustmentDossier
    {
        $this->guardPreDecision($dossier);

        $dossier->update([
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_RESCHEDULED,
            'interview_scheduled_at' => $scheduledAt,
        ]);

        return $dossier->refresh();
    }

    public function recordNoShow(ScholarshipAdjustmentDossier $dossier): ScholarshipAdjustmentDossier
    {
        $this->guardPreDecision($dossier);

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_STUDENT_NO_SHOW,
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_STUDENT_NO_SHOW,
        ]);

        return $dossier->refresh();
    }

    /**
     * Complete the interview with minutes. The dossier becomes decidable.
     */
    public function complete(ScholarshipAdjustmentDossier $dossier, string $minutes, array $participants = []): ScholarshipAdjustmentDossier
    {
        $this->guardPreDecision($dossier);

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
            'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED,
            'minutes' => $minutes,
            'minutes_version' => $dossier->minutes_version + 1,
            'interview_participants' => $participants,
        ]);

        return $dossier->refresh();
    }

    /**
     * Edit minutes after completion (correction) — bumps the version so a
     * student who already confirmed an earlier version is invalidated. Allowed
     * up through the decision stage (interviewed/ready_for_decision); blocked
     * once approved/applied — a decided dossier's evidence is frozen.
     */
    public function editMinutes(ScholarshipAdjustmentDossier $dossier, string $minutes): ScholarshipAdjustmentDossier
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

        $dossier->update([
            'minutes' => $minutes,
            'minutes_version' => $dossier->minutes_version + 1,
        ]);

        return $dossier->refresh();
    }
}
