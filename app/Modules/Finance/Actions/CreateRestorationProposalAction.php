<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\DB;

/**
 * Proposes restoring the original scholarship award for an `applied`
 * adjustment whose target-semester results came back clean. The adjustment
 * row is never mutated — restoration state lives only on this table.
 */
class CreateRestorationProposalAction
{
    public function __construct(private readonly CampusPermissionReader $permissions) {}

    /**
     * $restoredAmount null = propose a full restore. Set = propose a partial
     * restore at that level (same unit convention as adjusted_amount).
     */
    public function run(int $adjustmentId, string $reason, int $proposerUserId, ?float $restoredAmount = null): ScholarshipRestorationProposal
    {
        return DB::transaction(function () use ($adjustmentId, $reason, $proposerUserId, $restoredAmount): ScholarshipRestorationProposal {
            $adjustment = ScholarshipSemesterAdjustment::query()->lockForUpdate()->find($adjustmentId);

            if ($adjustment === null) {
                throw new \DomainException('adjustment_not_found');
            }

            // Defense in depth: the proposer must hold restore_scholarship AT
            // the adjustment's campus (non-null — null returns the all-campus
            // union). Symmetric with the approve/reject server-side checks.
            $proposerCodes = $this->permissions->permissionCodesForUserId($proposerUserId, (int) $adjustment->campus_id);

            if (! in_array('restore_scholarship', $proposerCodes, true)) {
                throw new \DomainException('proposer_not_authorized');
            }

            // Duplicate guard (narrowed for repeat-after-partial): block while
            // a pending_approval proposal exists, or once an approved FULL
            // restore exists (nothing left to raise). An approved PARTIAL
            // restore allows a new proposal — floor validated below.
            $existingProposals = ScholarshipRestorationProposal::query()
                ->where('scholarship_semester_adjustment_id', $adjustmentId)
                ->whereIn('status', ScholarshipRestorationProposal::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get();

            $approvedProposals = $existingProposals->filter(fn (ScholarshipRestorationProposal $proposal) => $proposal->status === ScholarshipRestorationProposal::STATUS_APPROVED);

            $hasPending = $existingProposals->contains(fn (ScholarshipRestorationProposal $proposal) => $proposal->status === ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL);
            $hasApprovedFullRestore = $approvedProposals->contains(fn (ScholarshipRestorationProposal $proposal) => $proposal->restored_amount === null);

            if ($hasPending || $hasApprovedFullRestore) {
                throw new \DomainException('duplicate_active_proposal');
            }

            // Same tie-break the resolver's effectiveAdjustedAmount() uses —
            // the floor validated here and the amount actually charged later
            // must never disagree.
            $latestApproved = ScholarshipSemesterAdjustment::pickLatestApproved($approvedProposals);

            $floor = $latestApproved !== null && $latestApproved->restored_amount !== null
                ? (float) $latestApproved->restored_amount
                : (float) $adjustment->adjusted_amount;

            if ($restoredAmount !== null) {
                $originalAmount = (float) $adjustment->original_amount;

                if ($restoredAmount <= $floor || $restoredAmount >= $originalAmount) {
                    throw new \DomainException('restored_amount_out_of_bounds');
                }
            }

            return ScholarshipRestorationProposal::query()->create([
                'scholarship_semester_adjustment_id' => $adjustment->id,
                'student_id' => $adjustment->student_id,
                'campus_id' => $adjustment->campus_id,
                'evaluated_semester_id' => $adjustment->target_semester_id,
                'status' => ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL,
                'reason' => $reason,
                'restored_amount' => $restoredAmount,
                'proposed_by_user_id' => $proposerUserId,
            ]);
        });
    }
}
