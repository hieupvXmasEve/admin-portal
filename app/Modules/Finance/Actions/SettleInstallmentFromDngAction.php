<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Events\ChargeFullySettled;
use App\Modules\Finance\Jobs\PushNextInstallmentJob;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marks ALL installments linked to a DNG request as paid, then dispatches one
 * next-installment push job per affected charge AFTER the surrounding transaction commits.
 *
 * A DNG can be linked to multiple installments when CreateBatchDngFromChargesAction
 * bundles next-pending installments from several charges of the same fee_type for a
 * student. In the single-installment case (per-charge push from PushNextInstallmentAction),
 * the loop body runs once.
 *
 * Called from:
 * - DngWebhookService::handlePaymentEvent (real-time webhook path)
 * - DngReconciliationService (batch fallback path for missed webhooks)
 *
 * Idempotent: safe to call multiple times for the same DNG request. Already-paid
 * installments are skipped (no re-dispatch).
 *
 * Post-commit dispatch (DB::afterCommit) is essential: paying the installment must
 * be durable even if pushing the next one fails. Inline dispatch would roll back
 * together if the outer transaction errored after this call.
 */
class SettleInstallmentFromDngAction
{
    /**
     * @return Collection<int, FinanceChargeInstallment> installments settled by this call
     *                                                   (empty if DNG has no linked installments or all already paid)
     */
    public function handle(DngPaymentRequest $request): Collection
    {
        // FIN-15: lock the installment rows for this DNG request so a concurrent
        // webhook + reconciliation (or two webhook retries) cannot both read them
        // as collectible, both flip them to paid, and both dispatch a duplicate
        // PushNextInstallmentJob. The lock serialises settlement; the loser re-reads
        // an empty collectible set and is a clean no-op. dispatchAfterCommit then
        // fires the next-push/fully-settled side effects only once, post-commit.
        return DB::transaction(function () use ($request): Collection {
            // FIN-12: only settle installments that are still collectible. A cancelled
            // installment (e.g. its charge was voided) must never be resurrected to
            // paid by a late webhook for the same DNG request.
            $installments = FinanceChargeInstallment::query()
                ->where('dng_payment_request_id', $request->id)
                ->whereIn('status', [
                    FinanceChargeInstallment::STATUS_PENDING,
                    FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                ])
                ->lockForUpdate()
                ->get();

            if ($installments->isEmpty()) {
                // Legacy DNG (no installment link) OR all already settled — no-op.
                return new Collection;
            }

            $paidAt = $request->paid_at ?? now();

            foreach ($installments as $installment) {
                $installment->update([
                    'status' => FinanceChargeInstallment::STATUS_PAID,
                    'paid_at' => $paidAt,
                ]);

                Log::info('Installment settled from DNG', [
                    'dng_payment_request_id' => $request->id,
                    'installment_id' => $installment->id,
                    'finance_charge_id' => $installment->finance_charge_id,
                ]);
            }

            // For each unique charge touched: decide push-next vs fully-settled.
            $chargeIds = $installments->pluck('finance_charge_id')->unique()->values();

            foreach ($chargeIds as $chargeId) {
                $hasMorePending = FinanceChargeInstallment::query()
                    ->where('finance_charge_id', $chargeId)
                    ->where('status', FinanceChargeInstallment::STATUS_PENDING)
                    ->exists();

                if ($hasMorePending) {
                    $this->dispatchAfterCommit(fn () => PushNextInstallmentJob::dispatch((int) $chargeId));
                } else {
                    $charge = FinanceCharge::find($chargeId);
                    if ($charge !== null) {
                        $this->dispatchAfterCommit(fn () => ChargeFullySettled::dispatch($charge));
                    }
                }
            }

            return $installments->map(fn ($i) => $i->fresh());
        });
    }

    /**
     * Run the callback after the surrounding transaction commits, or immediately
     * if not inside a transaction.
     */
    private function dispatchAfterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);
        } else {
            $callback();
        }
    }
}
