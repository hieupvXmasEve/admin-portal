<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;

/**
 * The single lookup every scholarship money surface uses to find the active
 * per-semester adjustment for a student. Only pending_apply and applied rows
 * drive money: a finance_review_required row must NEVER be auto-applied by
 * any generation surface (that is the whole point of the review status), and
 * reversed rows are closed. An adjustment stays `applied` for its target
 * semester forever (restoration is a Phase 5 proposal, never a status flip).
 *
 * NOTE: never resolve original award terms via InvoiceDiscount::scholarship()
 * — that relation joins scholarship_definitions.id against the stored AWARD
 * id and is miswired; always read this row's snapshot columns instead.
 */
class GetActiveScholarshipAdjustmentQuery
{
    public function handle(int $studentId, int $semesterId): ?ScholarshipSemesterAdjustment
    {
        return ScholarshipSemesterAdjustment::query()
            ->where('student_id', $studentId)
            ->where('target_semester_id', $semesterId)
            ->whereIn('status', [
                ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY,
                ScholarshipSemesterAdjustment::STATUS_APPLIED,
            ])
            ->latest('id')
            ->first();
    }
}
