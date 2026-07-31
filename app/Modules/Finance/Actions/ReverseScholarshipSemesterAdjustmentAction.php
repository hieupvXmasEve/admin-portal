<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Exceptions\InstallmentReconciliationException;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Support\ScholarshipAdjustmentTimingGuard;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentResult;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\DB;

/**
 * Reverse an active adjustment: mark it `reversed` and restore the discount
 * to the ORIGINAL award terms. Applied rows are immutable — this is the only
 * legal correction path (never an amount update).
 *
 * Unlike apply (where the Academic approval must survive ledger refusals), a
 * reversal is an operator action and is refused atomically: guard says review
 * or reconciliation throws → nothing changes, operator resolves manually.
 */
class ReverseScholarshipSemesterAdjustmentAction
{
    public function __construct(
        private readonly ScholarshipAdjustmentTimingGuard $timingGuard,
        private readonly ApplyScholarshipSemesterAdjustmentAction $applyAction,
        private readonly CampusPermissionReader $permissions,
    ) {}

    public function handle(int $adjustmentId, int $actorUserId): ScholarshipAdjustmentResult
    {
        $adjustment = ScholarshipSemesterAdjustment::query()->find($adjustmentId);

        if ($adjustment === null) {
            return ScholarshipAdjustmentResult::rejected('adjustment_not_found', 'Adjustment does not exist.');
        }

        if ($adjustment->status === ScholarshipSemesterAdjustment::STATUS_REVERSED) {
            return ScholarshipAdjustmentResult::rejected('already_reversed', 'Adjustment is already reversed.');
        }

        // Money-moving operation: the actor must hold the checker permission
        // at the adjustment's campus (non-null — null returns the all-campus union).
        $actorCodes = $this->permissions->permissionCodesForUserId($actorUserId, (int) $adjustment->campus_id);

        if (! in_array('approve_scholarship_adjustment', $actorCodes, true)) {
            return ScholarshipAdjustmentResult::rejected(
                'actor_not_authorized',
                "Actor lacks approve_scholarship_adjustment at campus {$adjustment->campus_id}.",
            );
        }

        try {
            // Guard evaluated INSIDE the transaction to shrink the window
            // between the safety read and the ledger write.
            DB::transaction(function () use ($adjustment, $actorUserId): void {
                $guard = $this->timingGuard->evaluate((int) $adjustment->student_id, (int) $adjustment->target_semester_id);

                if ($guard['outcome'] === ScholarshipAdjustmentTimingGuard::OUTCOME_REVIEW) {
                    throw new \DomainException('finance_review_required');
                }

                $adjustment->update([
                    'status' => ScholarshipSemesterAdjustment::STATUS_REVERSED,
                    'reversed_by_user_id' => $actorUserId,
                    'reversed_at' => now(),
                ]);

                if ($guard['invoice'] !== null) {
                    // Restore the unadjusted resolution (adjustment = null).
                    $this->applyAction->refreshScholarshipDiscount($guard['invoice'], null);
                }
            });
        } catch (\DomainException $e) {
            return ScholarshipAdjustmentResult::rejected(
                'finance_review_required',
                'Invoice has payments or active DNG activity — reverse manually via finance review.',
            );
        } catch (InstallmentReconciliationException $e) {
            return ScholarshipAdjustmentResult::rejected(
                'reconciliation_refused',
                "Installment reconciliation refused the restore: {$e->getMessage()}",
            );
        }

        return new ScholarshipAdjustmentResult(
            true,
            ScholarshipSemesterAdjustment::STATUS_REVERSED,
            (int) $adjustment->id,
        );
    }
}
