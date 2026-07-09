<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Exceptions\ChargeHasPaidInstallmentException;
use App\Modules\Finance\Exceptions\InstallmentSplitNotAllowedException;
use App\Modules\Finance\Exceptions\InvalidInstallmentPlanException;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Replace a charge's installment plan with a new N-row plan.
 *
 * Phase 1 invariants enforced here (not in FormRequest):
 * - Charge must be ACTIVE and have amount > 0 (credits cannot be split).
 * - Net split target = charge.amount - charge.discount_amount (matches
 *   DngPaymentService behavior which already pushes $charges->sum('balance')).
 * - sum(installment.amount) must equal net split target exactly (decimal-safe).
 * - installment_no must be 1..N contiguous, unique.
 * - due_date must be ascending by installment_no.
 * - Charge with any paid installment is locked (no replan in Phase 1).
 *
 * Only `pending` installments are replaced. If any non-pending installment
 * exists (awaiting_payment/paid/cancelled), the action throws — admin must
 * resolve them via DNG flow first.
 */
class SplitChargeIntoInstallmentsAction
{
    /**
     * Decimal precision (cents) used for the sum-equality check.
     * MariaDB decimal(15,2) → comparing in integer cents avoids float drift.
     */
    private const AMOUNT_SCALE = 2;

    /**
     * @param  array<int, array{installment_no:int, amount:float|string, due_date:string}>  $installments
     * @return Collection<int, FinanceChargeInstallment>
     *
     * @throws ChargeHasPaidInstallmentException
     * @throws InstallmentSplitNotAllowedException
     * @throws InvalidInstallmentPlanException
     */
    public function handle(int $chargeId, array $installments): Collection
    {
        return DB::transaction(function () use ($chargeId, $installments) {
            // Row-level lock to serialize concurrent split attempts on the same charge.
            $charge = FinanceCharge::query()
                ->lockForUpdate()
                ->findOrFail($chargeId);

            $this->assertChargeEligible($charge);
            $this->assertPlanReplaceable($charge);

            $netTarget = $this->computeNetTarget($charge);
            $this->assertPlanShape($installments, $netTarget);

            $this->deleteExistingPending($charge);
            $rows = $this->insertPlan($charge, $installments);

            return FinanceChargeInstallment::query()
                ->whereIn('id', $rows)
                ->orderBy('installment_no')
                ->get();
        });
    }

    private function assertChargeEligible(FinanceCharge $charge): void
    {
        if ($charge->status !== FinanceCharge::STATUS_ACTIVE) {
            throw new InstallmentSplitNotAllowedException(
                'charge_not_active',
                "FinanceCharge #{$charge->id} is not ACTIVE (status={$charge->status})."
            );
        }

        if ((float) $charge->amount <= 0) {
            throw new InstallmentSplitNotAllowedException(
                'charge_is_credit',
                "FinanceCharge #{$charge->id} has non-positive amount ({$charge->amount}). ".
                'Credits are settled via invoice allocation and cannot be split.'
            );
        }
    }

    private function assertPlanReplaceable(FinanceCharge $charge): void
    {
        if ($charge->hasPaidInstallment()) {
            throw new ChargeHasPaidInstallmentException($charge->id);
        }
    }

    /**
     * Net split target = gross amount - discount allocations on this charge's
     * invoice lines. Mirrors DngPaymentService push amount.
     */
    private function computeNetTarget(FinanceCharge $charge): float
    {
        $net = (float) $charge->amount - (float) $charge->discount_amount;

        if ($net <= 0) {
            throw new InstallmentSplitNotAllowedException(
                'charge_fully_credited',
                "FinanceCharge #{$charge->id} has net split target <= 0 (amount=".
                "{$charge->amount}, discount={$charge->discount_amount}). Nothing to collect."
            );
        }

        return $net;
    }

    /**
     * @param  array<int, array{installment_no:int, amount:float|string, due_date:string}>  $installments
     */
    private function assertPlanShape(array $installments, float $netTarget): void
    {
        if ($installments === []) {
            throw new InvalidInstallmentPlanException(
                'installment_plan_empty',
                'Installment plan must contain at least 1 row.'
            );
        }

        // Sort by installment_no for sequential + ascending-date checks.
        usort($installments, fn ($a, $b) => $a['installment_no'] <=> $b['installment_no']);

        $expectedNo = 1;
        $previousDate = null;
        $sumCents = 0;

        foreach ($installments as $row) {
            if ((int) $row['installment_no'] !== $expectedNo) {
                throw new InvalidInstallmentPlanException(
                    'installment_no_not_sequential',
                    "Installment numbers must be 1..N contiguous. Got {$row['installment_no']}, expected {$expectedNo}."
                );
            }

            $dueDate = Carbon::parse($row['due_date'])->toDateString();

            if ($previousDate !== null && $dueDate < $previousDate) {
                throw new InvalidInstallmentPlanException(
                    'due_date_not_ascending',
                    "Installment #{$row['installment_no']} due_date ({$dueDate}) is earlier than previous ({$previousDate})."
                );
            }

            $sumCents += $this->toCents($row['amount']);
            $previousDate = $dueDate;
            $expectedNo++;
        }

        $targetCents = $this->toCents($netTarget);

        if ($sumCents !== $targetCents) {
            $sumDisplay = number_format($sumCents / 100, self::AMOUNT_SCALE, '.', '');
            $targetDisplay = number_format($targetCents / 100, self::AMOUNT_SCALE, '.', '');

            throw new InvalidInstallmentPlanException(
                'installment_sum_mismatch',
                "Sum of installment amounts ({$sumDisplay}) does not equal net split target ({$targetDisplay})."
            );
        }
    }

    private function deleteExistingPending(FinanceCharge $charge): void
    {
        // Hard-guard: refuse if non-pending installments exist. Should be unreachable
        // here because hasPaidInstallment() already covered `paid`; but `awaiting_payment`
        // or `cancelled` blocks replan too — admin must cancel DNG first.
        $blocking = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->whereNotIn('status', [FinanceChargeInstallment::STATUS_PENDING])
            ->exists();

        if ($blocking) {
            throw new InvalidInstallmentPlanException(
                'charge_has_active_installment',
                "FinanceCharge #{$charge->id} has non-pending installments. ".
                'Cancel awaiting DNG requests before re-splitting.'
            );
        }

        FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->delete();
    }

    /**
     * @param  array<int, array{installment_no:int, amount:float|string, due_date:string}>  $installments
     * @return array<int, int> inserted installment IDs
     */
    private function insertPlan(FinanceCharge $charge, array $installments): array
    {
        $now = now();
        $ids = [];

        foreach ($installments as $row) {
            $created = FinanceChargeInstallment::create([
                'finance_charge_id' => $charge->id,
                'installment_no' => (int) $row['installment_no'],
                'amount' => $row['amount'],
                'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                'status' => FinanceChargeInstallment::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $ids[] = $created->id;
        }

        return $ids;
    }

    /**
     * Convert decimal amount to integer cents. Avoids float comparison drift
     * across split plans (e.g. 7500000.00 + 7500000.00 vs 15000000.00).
     */
    private function toCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
