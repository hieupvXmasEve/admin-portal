<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\FinanceSetting;

/**
 * Preview-only projection of the credit offset that
 * CreateFinanceChargeAction::offsetWithUnappliedCredit() applies after a
 * charge commits. Shared by the Major (HP) and EGC batch-charge previews so
 * both fee categories show the same approximation instead of drifting apart.
 *
 * Known divergence from the real offset (documented, accepted): this reads
 * settlement-position unapplied cash (subtracts PaymentSurplusDisposition
 * rows), while the real offset iterates each Payment's raw unapplied_amount
 * (disposition-blind). A student with a disposed surplus can see a real
 * deduction larger than what this preview projects.
 */
final class CreditOffsetProjector
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $positionReader,
    ) {}

    /**
     * @param  int[]  $studentIds
     * @return array<int, float> studentId => unapplied cash
     */
    public function unappliedCashForStudents(array $studentIds): array
    {
        return $this->positionReader->unappliedCashForStudents($studentIds);
    }

    /**
     * @return array{unapplied_credit: float, credit_offset_projected: float}
     */
    public function project(float $unappliedCash, float $netAmount, FinanceSetting $settings): array
    {
        $projected = 0.0;

        if ($settings->credit_offset_enabled
            && $unappliedCash > 0
            && $unappliedCash >= (float) $settings->credit_offset_min_balance
        ) {
            $projected = min($unappliedCash, $netAmount);
        }

        return [
            'unapplied_credit' => $unappliedCash,
            'credit_offset_projected' => $projected,
        ];
    }
}
