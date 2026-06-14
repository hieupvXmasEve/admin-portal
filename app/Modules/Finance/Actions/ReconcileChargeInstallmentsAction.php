<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Modules\Finance\Exceptions\InstallmentReconciliationException;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Collection;

/**
 * Keep a charge's PENDING installments in sync with its net due after a discount
 * changes it (FIN-09).
 *
 * At split time sum(installments) == net due. A discount applied *after* the
 * split lowers (or raises) net due without touching the installment rows, so the
 * plan silently drifts. This action recomputes the pending rows to absorb the
 * delta, preserving installment_no and due_date, and never touches committed
 * (awaiting_payment / paid) rows. When committed rows alone already exceed the
 * new net due it throws, surfacing the case for human review instead of rewriting
 * an already-pushed DNG amount.
 */
class ReconcileChargeInstallmentsAction
{
    /** Integer-cents scale for drift-free comparison against decimal(15,2). */
    private const AMOUNT_SCALE = 2;

    public function __construct(
        private readonly SettlementService $settlementService,
    ) {}

    public function handle(FinanceCharge $charge): void
    {
        $installments = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->orderBy('installment_no')
            ->get();

        if ($installments->isEmpty()) {
            return; // no plan to reconcile
        }

        $pending = $installments
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->values();

        $committedCents = $installments
            ->whereIn('status', [
                FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                FinanceChargeInstallment::STATUS_PAID,
            ])
            ->sum(fn (FinanceChargeInstallment $i) => $this->toCents($i->amount));

        $netDueCents = $this->toCents($this->netDue($charge));
        $pendingTargetCents = $netDueCents - $committedCents;

        $currentPendingCents = $pending
            ->sum(fn (FinanceChargeInstallment $i) => $this->toCents($i->amount));

        // Already consistent — nothing drifted.
        if ($currentPendingCents === $pendingTargetCents) {
            return;
        }

        // Committed rows alone already meet/exceed net due, but pending rows still
        // expect to collect more (or net due dropped below what was committed):
        // cannot fix without changing committed/pushed installments.
        if ($pendingTargetCents < 0) {
            throw new InstallmentReconciliationException(
                'committed_exceeds_net_due',
                "FinanceCharge #{$charge->id}: committed installments (".
                $this->fromCents($committedCents).') exceed net due ('.
                $this->fromCents($netDueCents).') after a discount change. '.
                'Resolve the pushed/paid installments before re-discounting.'
            );
        }

        if ($pending->isEmpty()) {
            // No pending rows to absorb the delta, yet drift exists.
            throw new InstallmentReconciliationException(
                'no_pending_to_reconcile',
                "FinanceCharge #{$charge->id}: net due changed to ".
                $this->fromCents($netDueCents).' but all installments are committed; '.
                'no pending row can absorb the difference.'
            );
        }

        if ($pendingTargetCents === 0) {
            // Discount fully covers the remaining balance — cancel pending rows.
            foreach ($pending as $row) {
                $row->update(['status' => FinanceChargeInstallment::STATUS_CANCELLED]);
            }

            return;
        }

        $this->redistribute($pending, $pendingTargetCents, $currentPendingCents);
    }

    /**
     * Net due = gross charge amount - active discount allocations on the charge.
     */
    private function netDue(FinanceCharge $charge): float
    {
        return max(0.0, (float) $charge->amount - $this->settlementService->getChargeDiscountAmount($charge->id));
    }

    /**
     * Spread targetCents across the pending rows proportionally to their current
     * amounts (equal split when current sum is zero), giving the cents remainder
     * to the last row so the rounded parts always sum exactly to target.
     *
     * @param  Collection<int, FinanceChargeInstallment>  $pending
     */
    private function redistribute($pending, int $targetCents, int $currentPendingCents): void
    {
        $count = $pending->count();
        $allocated = 0;

        foreach ($pending->values() as $index => $row) {
            $isLast = $index === $count - 1;

            if ($isLast) {
                $shareCents = $targetCents - $allocated;
            } elseif ($currentPendingCents > 0) {
                $shareCents = (int) floor($targetCents * ($this->toCents($row->amount) / $currentPendingCents));
            } else {
                $shareCents = intdiv($targetCents, $count);
            }

            $allocated += $shareCents;
            $row->update(['amount' => $this->fromCents($shareCents)]);
        }
    }

    private function toCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, self::AMOUNT_SCALE, '.', '');
    }
}
