<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * Rejects a restoration proposal. The adjustment keeps carrying forward its
 * reduced rate (the generation gate never falls back to the original rate on
 * rejection) — re-proposal is allowed later with a new reason.
 */
class RejectRestorationProposalAction
{
    public function __construct(private readonly CampusPermissionReader $permissions) {}

    public function run(int $proposalId, int $actorUserId): ScholarshipRestorationProposal
    {
        $proposal = ScholarshipRestorationProposal::query()->find($proposalId);

        if ($proposal === null) {
            throw new \DomainException('proposal_not_found');
        }

        if ($proposal->status !== ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL) {
            throw new \DomainException('proposal_not_pending');
        }

        $actorCodes = $this->permissions->permissionCodesForUserId($actorUserId, (int) $proposal->campus_id);

        if (! in_array('approve_scholarship_adjustment', $actorCodes, true)) {
            throw new \DomainException('actor_not_authorized');
        }

        $proposal->update(['status' => ScholarshipRestorationProposal::STATUS_REJECTED]);

        return $proposal->fresh();
    }
}
