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

    public function run(int $adjustmentId, string $reason, int $proposerUserId): ScholarshipRestorationProposal
    {
        return DB::transaction(function () use ($adjustmentId, $reason, $proposerUserId): ScholarshipRestorationProposal {
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

            // Idempotency: refuse a second active proposal for the same
            // adjustment (pending_approval or already approved).
            $hasActiveProposal = ScholarshipRestorationProposal::query()
                ->where('scholarship_semester_adjustment_id', $adjustmentId)
                ->whereIn('status', ScholarshipRestorationProposal::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($hasActiveProposal) {
                throw new \DomainException('duplicate_active_proposal');
            }

            return ScholarshipRestorationProposal::query()->create([
                'scholarship_semester_adjustment_id' => $adjustment->id,
                'student_id' => $adjustment->student_id,
                'campus_id' => $adjustment->campus_id,
                'evaluated_semester_id' => $adjustment->target_semester_id,
                'status' => ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL,
                'reason' => $reason,
                'proposed_by_user_id' => $proposerUserId,
            ]);
        });
    }
}
