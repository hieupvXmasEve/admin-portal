<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Exceptions\InstallmentReconciliationException;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keep a charge's PENDING installments in sync with its net due after a discount
 * changes it (FIN-09).
 *
 * At split time sum(installments) == net due. A discount applied *after* the
 * split lowers (or raises) net due without touching the installment rows, so the
 * plan silently drifts. This action recomputes the pending rows to absorb the
 * delta, preserving installment_no and due_date, and never rewrites an
 * awaiting_payment or paid row.
 *
 * The reconciliation target is the canonical remaining balance, which is already
 * net of every payment applied to the charge. So only awaiting_payment rows —
 * pushed to the provider but not yet collected — are subtracted on top of it;
 * paid rows are represented by the payments themselves. When the rows still
 * awaiting collection alone exceed the remaining balance it throws, surfacing the
 * case for human review instead of rewriting an already-pushed DNG amount.
 */
class ReconcileChargeInstallmentsAction
{
    /** Integer-cents scale for drift-free comparison against decimal(15,2). */
    private const AMOUNT_SCALE = 2;

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
    ) {}

    public function handle(FinanceCharge $charge): void
    {
        DB::transaction(function () use ($charge): void {
            $installments = FinanceChargeInstallment::query()
                ->where('finance_charge_id', $charge->id)
                ->orderBy('installment_no')
                ->lockForUpdate()
                ->get();

            if ($installments->isEmpty()) {
                return; // no plan to reconcile
            }

            $pending = $installments
                ->where('status', FinanceChargeInstallment::STATUS_PENDING)
                ->values();

            // Only awaiting_payment counts as committed-but-uncollected. A paid
            // installment's cash is already deducted from the canonical remaining
            // below, so counting it here would subtract the same money twice and
            // drive the pending target negative on any plan with a settled row.
            $committedCents = $installments
                ->where('status', FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
                ->sum(fn (FinanceChargeInstallment $i) => $this->toCents($i->amount));

            $collectibleCents = $this->canonicalCollectible($charge);
            $pendingTargetCents = $collectibleCents - $committedCents;

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
                if ($this->markHoldingDngRequestsForReview($charge, $committedCents, $collectibleCents)) {
                    return;
                }

                throw new InstallmentReconciliationException(
                    'committed_exceeds_net_due',
                    "FinanceCharge #{$charge->id}: committed installments (".
                    $this->fromCents($committedCents).') exceed net due ('.
                    $this->fromCents($collectibleCents).') after a settlement mutation. '.
                    'Resolve the pushed/paid installments before re-discounting.'
                );
            }

            if ($pending->isEmpty()) {
                // No pending rows to absorb the delta, yet drift exists.
                throw new InstallmentReconciliationException(
                    'no_pending_to_reconcile',
                    "FinanceCharge #{$charge->id}: net due changed to ".
                    $this->fromCents($collectibleCents).' but all installments are committed; '.
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
        });
    }

    /**
     * Preserve the provider request and its captured targets while making the
     * local collection hold explicitly repairable by staff.
     */
    private function markHoldingDngRequestsForReview(FinanceCharge $charge, int $committedCents, int $collectibleCents): bool
    {
        $lineIds = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return false;
        }

        $evidence = sprintf(
            'Cần kiểm tra: committed installments exceed canonical collectible for FinanceCharge #%d (committed=%s, collectible=%s). Provider request and reserved targets were preserved; staff review is required before changing the collection plan.',
            $charge->id,
            $this->fromCents($committedCents),
            $this->fromCents($collectibleCents),
        );

        return DngPaymentRequest::query()
            ->whereIn('status', [
                DngPaymentRequest::STATUS_PENDING,
                DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
            ])
            ->whereHas('reservationTargets', fn ($query) => $query
                ->whereIn('invoice_line_id', $lineIds))
            ->lockForUpdate()
            ->update([
                'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
                'error_message' => $evidence,
            ]) > 0;
    }

    /** Canonical collectible includes applied credit and cash, never a local formula. */
    private function canonicalCollectible(FinanceCharge $charge): int
    {
        $lineIds = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $position = $this->settlementPositionReader->forPayableLines($lineIds);

        if (! $position->isValid() || $position->amounts === null) {
            throw new InstallmentReconciliationException(
                'settlement_position_invalid',
                "FinanceCharge #{$charge->id}: Cần kiểm tra Settlement Position before reconciling installments.",
            );
        }

        return $this->toCents($position->amounts->remaining->amount);
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
