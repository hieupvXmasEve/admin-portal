<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ops-facing list of `applied` adjustments still awaiting an explicit
 * restoration close-out for a campus — every case the generation gate
 * (GetUnresolvedPriorAdjustmentQuery) may currently be carrying forward.
 */
class ListPendingRestorationReviewsQuery
{
    /** @return Collection<int, ScholarshipSemesterAdjustment> */
    public function handle(int $campusId): Collection
    {
        return ScholarshipSemesterAdjustment::query()
            ->where('campus_id', $campusId)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_APPLIED)
            ->whereDoesntHave('restorationProposals', fn ($query) => $query
                ->where('status', ScholarshipRestorationProposal::STATUS_APPROVED))
            ->with(['student', 'targetSemester'])
            ->orderBy('target_semester_id')
            ->get();
    }
}
