<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Semester;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;

/**
 * Set-based (once per batch run, not per student) — Finance-internal, no
 * Shared contract needed since both the restoration proposal and the
 * adjustment it evaluates are Finance-owned (unlike
 * PendingScholarshipAdjustmentReader, which crosses into Academic).
 *
 * Same carry-forward predicate as GetUnresolvedPriorAdjustmentQuery, plus a
 * check for a pending_approval restoration proposal on that carried
 * adjustment. A pending proposal means a decision is imminent — generating
 * tuition at today's rate risks a stale charge that then needs manual
 * correction the moment the proposal is approved (mirrors the shipped
 * "generate XOR reduce" invariant from plan 260807).
 *
 * Bakes in GetUnresolvedPriorAdjustmentQuery's own precondition ("callers use
 * this ONLY after GetActiveScholarshipAdjustmentQuery for the current
 * semester returns null") rather than requiring every caller to pre-filter:
 * a student with their OWN pending_apply/applied adjustment targeting the
 * CURRENT semester is excluded — that adjustment (not the older carried one)
 * governs this semester's money, and its own restoration proposal, if any,
 * is irrelevant to whether tuition should generate now.
 */
class PendingScholarshipRestorationReader
{
    /**
     * @param  int[]  $studentIds
     * @return array<int,bool> studentId => hasPendingRestorationOnCarriedAdjustment(currentSemesterId); every input id present
     */
    public function pendingByStudent(array $studentIds, int $currentSemesterId): array
    {
        $map = array_fill_keys($studentIds, false);

        if ($studentIds === []) {
            return $map;
        }

        $currentSemester = Semester::query()->find($currentSemesterId);

        if ($currentSemester === null || $currentSemester->start_date === null) {
            return $map;
        }

        $studentIdsWithActiveCurrentSemesterAdjustment = ScholarshipSemesterAdjustment::query()
            ->whereIn('student_id', $studentIds)
            ->where('target_semester_id', $currentSemesterId)
            ->whereIn('status', [
                ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY,
                ScholarshipSemesterAdjustment::STATUS_APPLIED,
            ])
            ->pluck('student_id');

        $studentIdsWithPending = ScholarshipSemesterAdjustment::query()
            ->whereIn('student_id', $studentIds)
            ->whereNotIn('student_id', $studentIdsWithActiveCurrentSemesterAdjustment)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_APPLIED)
            ->whereHas('targetSemester', fn ($query) => $query
                ->whereNotNull('start_date')
                ->where('start_date', '<', $currentSemester->start_date))
            ->whereDoesntHave('restorationProposals', fn ($query) => $query
                ->where('status', ScholarshipRestorationProposal::STATUS_APPROVED)
                ->whereNull('restored_amount'))
            ->whereHas('restorationProposals', fn ($query) => $query
                ->where('status', ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL))
            ->pluck('student_id')
            ->unique();

        foreach ($studentIdsWithPending as $studentId) {
            $map[$studentId] = true;
        }

        return $map;
    }
}
