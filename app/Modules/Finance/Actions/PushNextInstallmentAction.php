<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Events\InstallmentPushed;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Illuminate\Support\Facades\Log;

/**
 * Push the next pending installment of a charge to DNG.
 *
 * Triggered:
 * - Auto: by SettleInstallmentFromDngAction after a DNG payment is reconciled
 *   (post-commit dispatch via PushNextInstallmentJob).
 * - Manual: by admin clicking the "Retry push" button on an installment with
 *   last_push_error.
 *
 * Concurrency:
 * - SELECT ... FOR UPDATE on the parent charge serializes 2 webhooks for the
 *   same charge arriving concurrently. Only one will pass the "find next pending"
 *   gate.
 *
 * Returns the touched installment (now awaiting_payment) on success, or null
 * when there are no pending installments left (caller should fire
 * ChargeFullySettled in that case — wired in SettleInstallmentFromDngAction).
 *
 * Throws on DNG HTTP failure; PushNextInstallmentJob handles retry.
 */
class PushNextInstallmentAction
{
    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    public function handle(int $chargeId, ?int $explicitInstallmentId = null): ?FinanceChargeInstallment
    {
        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) FinanceCharge::query()->findOrFail($chargeId)->student_id)
            ->id;

        // Lock the charge row to serialize concurrent pushes (webhook + manual).
        $charge = FinanceCharge::query()
            ->with('student')
            ->lockForUpdate()
            ->findOrFail($chargeId);

        $installment = $this->resolveTarget($charge, $explicitInstallmentId);

        if ($installment === null) {
            return null;
        }

        $student = $charge->student;

        if ($student === null) {
            throw new \RuntimeException(
                "FinanceCharge #{$charge->id} has no student attached. Cannot push installment."
            );
        }

        $line = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->firstOrFail();
        $dngFeeType = $this->mapFeeType($charge->charge_type);
        try {
            $reservation = app(ReserveAndPushSingleFeeDngAction::class)->handle(
                $student->id,
                $dngFeeType,
                [
                    'description' => $charge->description.' (Đợt '.$installment->installment_no.')',
                    'semester_id' => $charge->semester_id,
                    'due_date' => $installment->due_date->toDateString(),
                    'estimate_time' => $installment->due_date->format('m/y'),
                ],
                [(int) $line->id],
                [(int) $line->id => (string) $installment->amount],
                [(int) $line->id => (int) $installment->id],
            );
        } catch (\Throwable $exception) {
            $this->settlementMutationGuard->handle($billingAccountId, function () use ($installment, $exception): void {
                $lockedInstallment = FinanceChargeInstallment::query()
                    ->lockForUpdate()
                    ->findOrFail($installment->id);

                $lockedInstallment->update([
                    'last_push_error' => $exception->getMessage(),
                    'last_push_attempted_at' => now(),
                    'push_attempt_count' => $lockedInstallment->push_attempt_count + 1,
                ]);
            });

            throw $exception;
        }

        $fresh = $this->settlementMutationGuard->handle($billingAccountId, function () use ($installment, $reservation): FinanceChargeInstallment {
            $lockedInstallment = FinanceChargeInstallment::query()
                ->lockForUpdate()
                ->findOrFail($installment->id);

            $lockedInstallment->update([
                'dng_payment_request_id' => $reservation->id,
                'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                'last_push_error' => null,
                'last_push_attempted_at' => now(),
            ]);

            return $lockedInstallment->fresh();
        });

        Log::info('Installment pushed to DNG', [
            'finance_charge_id' => $charge->id,
            'installment_id' => $installment->id,
            'installment_no' => $installment->installment_no,
            'amount' => $installment->amount,
        ]);

        InstallmentPushed::dispatch($fresh);

        return $fresh;
    }

    /**
     * Resolve which installment to push:
     * - If explicit ID given (manual retry button): use it, validate it belongs to charge + is pending.
     * - Else: pick lowest-numbered pending installment.
     */
    private function resolveTarget(FinanceCharge $charge, ?int $explicitId): ?FinanceChargeInstallment
    {
        if ($explicitId !== null) {
            $installment = FinanceChargeInstallment::query()
                ->where('id', $explicitId)
                ->where('finance_charge_id', $charge->id)
                ->lockForUpdate()
                ->first();

            if ($installment === null) {
                throw new \RuntimeException(
                    "Installment #{$explicitId} not found or does not belong to charge #{$charge->id}."
                );
            }

            if ($installment->status !== FinanceChargeInstallment::STATUS_PENDING) {
                throw new \RuntimeException(
                    "Installment #{$explicitId} is not pending (status={$installment->status}). Cannot retry push."
                );
            }

            return $installment;
        }

        return FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->orderBy('installment_no')
            ->lockForUpdate()
            ->first();
    }

    /** Map the charge type through the supported provider collection family. */
    private function mapFeeType(string $chargeType): string
    {
        return match ($chargeType) {
            FinanceCharge::TYPE_TUITION_TERM,
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'HP',
            FinanceCharge::TYPE_RETAKE_FEE => 'HL',
            FinanceCharge::TYPE_EXAM_RESIT_FEE => 'PTL',
            FinanceCharge::TYPE_BHYT => 'BHYT',
            default => throw new \InvalidArgumentException("Unsupported DNG charge type {$chargeType}."),
        };
    }
}
