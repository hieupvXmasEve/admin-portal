<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Shared\Contracts\Academic\ScholarshipDossierCloser;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\DB;

/**
 * Approves a restoration proposal: maker (proposer) != checker (approver),
 * checker re-verified server-side at the proposal's campus. On approval, the
 * Academic dossier is closed through the Shared contract — Finance resolves
 * the dossier id from its own adjustment snapshot (academic_dossier_id) and
 * hands it to Academic, never the other way around.
 */
class ApproveRestorationProposalAction
{
    public function __construct(
        private readonly CampusPermissionReader $permissions,
        private readonly ScholarshipDossierCloser $dossierCloser,
    ) {}

    public function run(int $proposalId, int $approverUserId): ScholarshipRestorationProposal
    {
        return DB::transaction(function () use ($proposalId, $approverUserId): ScholarshipRestorationProposal {
            // lockForUpdate closes the double-approve race: two concurrent
            // checkers must not both pass the pending check and both write.
            $proposal = ScholarshipRestorationProposal::query()
                ->with('adjustment')
                ->lockForUpdate()
                ->find($proposalId);

            if ($proposal === null) {
                throw new \DomainException('proposal_not_found');
            }

            if ($proposal->status !== ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL) {
                throw new \DomainException('proposal_not_pending');
            }

            if ((int) $proposal->proposed_by_user_id === $approverUserId) {
                throw new \DomainException('maker_is_checker');
            }

            // Campus MUST be non-null — a null campus returns the all-campus
            // union and would accept an approver with the permission anywhere.
            $approverCodes = $this->permissions->permissionCodesForUserId($approverUserId, (int) $proposal->campus_id);

            if (! in_array('approve_scholarship_adjustment', $approverCodes, true)) {
                throw new \DomainException('checker_not_authorized');
            }

            $proposal->update([
                'status' => ScholarshipRestorationProposal::STATUS_APPROVED,
                'approved_by_user_id' => $approverUserId,
                'approved_at' => now(),
            ]);

            $this->dossierCloser->closeDossier((int) $proposal->adjustment->academic_dossier_id);

            return $proposal->fresh();
        });
    }
}
