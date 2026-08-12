<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Finance\DTO\ScholarshipRestorationWatchlistRow;
use App\Shared\Contracts\Finance\ScholarshipRestorationWatchlistReader;
use Illuminate\Support\Collection;

/**
 * All `applied` adjustments a campus is still carrying (Phase 3 watchlist
 * data source) — every row GetUnresolvedPriorAdjustmentQuery may currently
 * be resolving, PLUS ones with no proposal yet, a pending one, or a rejected
 * one, so staff can see and act on the whole population, not just the
 * pending-review subset ListPendingRestorationReviewsQuery exists for.
 *
 * Excludes only an approved FULL restore — same rule
 * GetUnresolvedPriorAdjustmentQuery uses, so this list and the generation
 * gate never disagree on "still carrying".
 */
class ListCarriedScholarshipAdjustmentsQuery implements ScholarshipRestorationWatchlistReader
{
    /** @return Collection<int, ScholarshipRestorationWatchlistRow> */
    public function listCarried(int $campusId): Collection
    {
        $adjustments = ScholarshipSemesterAdjustment::query()
            ->where('campus_id', $campusId)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_APPLIED)
            ->whereDoesntHave('restorationProposals', fn ($query) => $query
                ->where('status', ScholarshipRestorationProposal::STATUS_APPROVED)
                ->whereNull('restored_amount'))
            ->with('restorationProposals')
            ->join('semesters as target', 'target.id', '=', 'scholarship_semester_adjustments.target_semester_id')
            ->orderByDesc('target.start_date')
            ->select('scholarship_semester_adjustments.*')
            ->get();

        return $adjustments->map(fn (ScholarshipSemesterAdjustment $adjustment) => $this->toRow($adjustment))->values();
    }

    private function toRow(ScholarshipSemesterAdjustment $adjustment): ScholarshipRestorationWatchlistRow
    {
        $proposals = $adjustment->restorationProposals;

        $pending = $proposals->first(fn (ScholarshipRestorationProposal $proposal) => $proposal->status === ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL);

        $latestApproved = ScholarshipSemesterAdjustment::pickLatestApproved(
            $proposals->filter(fn (ScholarshipRestorationProposal $proposal) => $proposal->status === ScholarshipRestorationProposal::STATUS_APPROVED),
        );

        $latestRejected = $proposals
            ->filter(fn (ScholarshipRestorationProposal $proposal) => $proposal->status === ScholarshipRestorationProposal::STATUS_REJECTED)
            ->sortByDesc(fn (ScholarshipRestorationProposal $proposal) => $proposal->id)
            ->first();

        // Priority mirrors staff urgency: a pending decision always surfaces
        // first, then the current approved-partial state, then the most
        // recent rejection, then no history at all.
        [$state, $proposalId, $restoredAmount] = match (true) {
            $pending !== null => [ScholarshipRestorationWatchlistRow::STATE_PENDING, (int) $pending->id, null],
            $latestApproved !== null && $latestApproved->restored_amount !== null => [
                ScholarshipRestorationWatchlistRow::STATE_APPROVED_PARTIAL,
                (int) $latestApproved->id,
                (float) $latestApproved->restored_amount,
            ],
            $latestRejected !== null => [ScholarshipRestorationWatchlistRow::STATE_REJECTED, (int) $latestRejected->id, null],
            default => [ScholarshipRestorationWatchlistRow::STATE_NONE, null, null],
        };

        $effectiveAmount = $restoredAmount ?? (float) $adjustment->adjusted_amount;

        return new ScholarshipRestorationWatchlistRow(
            adjustment_id: (int) $adjustment->id,
            student_id: (int) $adjustment->student_id,
            campus_id: (int) $adjustment->campus_id,
            target_semester_id: (int) $adjustment->target_semester_id,
            original_type: (string) $adjustment->original_type,
            original_amount: (float) $adjustment->original_amount,
            adjusted_amount: (float) $adjustment->adjusted_amount,
            effective_amount: $effectiveAmount,
            proposal_state: $state,
            latest_proposal_id: $proposalId,
            latest_restored_amount: $restoredAmount,
            academic_dossier_id: $adjustment->academic_dossier_id !== null ? (int) $adjustment->academic_dossier_id : null,
        );
    }
}
