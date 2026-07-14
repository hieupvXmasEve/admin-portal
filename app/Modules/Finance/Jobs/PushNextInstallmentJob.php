<?php

declare(strict_types=1);

namespace App\Modules\Finance\Jobs;

use App\Modules\Finance\Actions\PushNextInstallmentAction;
use App\Modules\Finance\Events\InstallmentPushFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wraps PushNextInstallmentAction with retry policy.
 *
 * Retry: 3 attempts total, exponential backoff: 60s → 300s → 900s (1m / 5m / 15m).
 * PushNextInstallmentAction records last_push_error and
 * push_attempt_count on each failure, so the installment row reflects current state.
 *
 * After final failure, the job's failed() callback fires the InstallmentPushFailed
 * event (Batch D will add the event class; until then we just log).
 */
class PushNextInstallmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * Exponential backoff: 1m → 5m → 15m. Index 0 is delay after attempt 1.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public readonly int $financeChargeId,
    ) {
        $this->onQueue('finance');
    }

    public function handle(PushNextInstallmentAction $action): void
    {
        try {
            $installment = $action->handle($this->financeChargeId);

            if ($installment === null) {
                Log::info('PushNextInstallmentJob: no pending installment left', [
                    'finance_charge_id' => $this->financeChargeId,
                ]);

                // Charge fully settled — Batch D will fire ChargeFullySettled event here.
                return;
            }

            Log::info('PushNextInstallmentJob: pushed', [
                'finance_charge_id' => $this->financeChargeId,
                'installment_id' => $installment->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('PushNextInstallmentJob: push attempt failed', [
                'finance_charge_id' => $this->financeChargeId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw so Laravel's retry machinery applies the backoff.
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('PushNextInstallmentJob: all retries exhausted', [
            'finance_charge_id' => $this->financeChargeId,
            'error' => $exception->getMessage(),
        ]);

        // Notify admin listeners. The installment row already has last_push_error
        // + push_attempt_count set by PushNextInstallmentAction, so admin UI
        // can show the error and manual-retry button.
        InstallmentPushFailed::dispatch($this->financeChargeId, $exception->getMessage());
    }
}
