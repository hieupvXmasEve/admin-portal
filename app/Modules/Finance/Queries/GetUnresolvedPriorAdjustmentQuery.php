<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Models\Semester;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;

/**
 * The generation-time restoration gate (Phase 5). Finds the latest `applied`
 * adjustment for a student whose target semester is strictly before the
 * given semester and has NO approved FULL-restore proposal — the case where
 * the original award must NOT be silently restored. A partial restoration
 * keeps the row carried — the caller MUST resolve the effective (possibly
 * restored) amount via ScholarshipSemesterAdjustment::effectiveAdjustedAmount()
 * and pass it as ScholarshipDiscountResolver::resolveAdjusted()'s
 * $effectiveAmountOverride; the returned model's own adjusted_amount
 * attribute is left untouched (it still reflects this adjustment's own
 * target-semester rate, used elsewhere for display).
 *
 * Callers use this ONLY after GetActiveScholarshipAdjustmentQuery for the
 * current semester returns null: a new adjustment for the current semester
 * always takes precedence over a carried-forward prior one.
 */
class GetUnresolvedPriorAdjustmentQuery
{
    public function handle(int $studentId, int $currentSemesterId): ?ScholarshipSemesterAdjustment
    {
        $currentSemester = Semester::query()->find($currentSemesterId);

        // No comparable start_date on the current semester: cannot establish
        // ordering, so nothing can be judged "prior" — skip rather than guess.
        if ($currentSemester === null || $currentSemester->start_date === null) {
            return null;
        }

        return ScholarshipSemesterAdjustment::query()
            ->where('student_id', $studentId)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_APPLIED)
            ->whereHas('targetSemester', fn ($query) => $query
                ->whereNotNull('start_date')
                ->where('start_date', '<', $currentSemester->start_date))
            ->whereDoesntHave('restorationProposals', fn ($query) => $query
                ->where('status', ScholarshipRestorationProposal::STATUS_APPROVED)
                ->whereNull('restored_amount'))
            // Avoid N+1 in the batch consumer: effectiveAdjustedAmount() reads
            // this relation directly instead of issuing a per-row query.
            ->with('approvedRestorationProposals')
            // Most recent prior by SEMESTER (start_date), not insert order — an
            // out-of-order backfill must not carry the wrong semester's rate.
            ->join('semesters as prior_target', 'prior_target.id', '=', 'scholarship_semester_adjustments.target_semester_id')
            ->orderByDesc('prior_target.start_date')
            ->select('scholarship_semester_adjustments.*')
            ->first();
    }
}
