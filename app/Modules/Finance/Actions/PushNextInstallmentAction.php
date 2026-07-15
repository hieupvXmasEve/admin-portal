<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngReservationLifecycle;
use App\Modules\Finance\Events\InstallmentPushed;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Closure;
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
 * - The payer guard, parent charge, installment rows, and invoice-line target
 *   are locked in one reservation transaction. The selected installment is
 *   linked to its DNG reservation before that transaction commits.
 * - The DNG provider call runs only after the reservation transaction commits.
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
        private readonly DngReservationLifecycle $dngReservationLifecycle,
    ) {}

    public function handle(int $chargeId, ?int $explicitInstallmentId = null): ?FinanceChargeInstallment
    {
        $charge = FinanceCharge::query()->findOrFail($chargeId);
        $hasUnsettledInstallment = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $chargeId)
            ->whereIn('status', [
                FinanceChargeInstallment::STATUS_PENDING,
                FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
            ])
            ->exists();
        if (! $hasUnsettledInstallment) {
            return null;
        }

        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) $charge->student_id)
            ->id;

        $selection = $this->settlementMutationGuard->handleIfChanged(
            $billingAccountId,
            function ($_billingAccount, Closure $markChanged) use ($chargeId, $explicitInstallmentId): ?array {
                $lockedCharge = FinanceCharge::query()
                    ->with('student')
                    ->lockForUpdate()
                    ->findOrFail($chargeId);
                $installment = $this->resolveTarget($lockedCharge, $explicitInstallmentId);

                if ($installment === null) {
                    return null;
                }

                $student = $lockedCharge->student;
                if ($student === null) {
                    throw new \RuntimeException(
                        "FinanceCharge #{$lockedCharge->id} has no student attached. Cannot push installment."
                    );
                }

                $line = InvoiceLine::query()
                    ->where('charge_id', $lockedCharge->id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->firstOrFail();
                $reservation = $this->dngReservationLifecycle->reserve(
                    $student->id,
                    $this->mapFeeType($lockedCharge->charge_type),
                    [
                        'description' => $lockedCharge->description.' (Đợt '.$installment->installment_no.')',
                        'semester_id' => $lockedCharge->semester_id,
                        'due_date' => $installment->due_date->toDateString(),
                        'estimate_time' => $installment->due_date->format('m/y'),
                    ],
                    [(int) $line->id],
                    [(int) $line->id => (string) $installment->amount],
                    [(int) $line->id => (int) $installment->id],
                );

                $isExactReservation = $reservation->reservationTargets()
                    ->where('finance_charge_installment_id', $installment->id)
                    ->exists();
                if (! $isExactReservation) {
                    throw new \RuntimeException(
                        "DNG reservation #{$reservation->id} does not own installment #{$installment->id}."
                    );
                }

                $installment->update([
                    'dng_payment_request_id' => $reservation->id,
                    'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                    'last_push_error' => null,
                    'last_push_attempted_at' => now(),
                ]);
                if (! $reservation->wasRecentlyCreated) {
                    $markChanged();
                }

                return [
                    'charge_id' => (int) $lockedCharge->id,
                    'installment_id' => (int) $installment->id,
                    'reservation_id' => (int) $reservation->id,
                    'student_id' => (int) $student->id,
                    'details' => [
                        'description' => $lockedCharge->description.' (Đợt '.$installment->installment_no.')',
                        'semester_id' => (int) $lockedCharge->semester_id,
                        'due_date' => $installment->due_date->toDateString(),
                        'estimate_time' => $installment->due_date->format('m/y'),
                    ],
                ];
            },
        );

        if ($selection === null) {
            return null;
        }

        try {
            $reservation = $this->dngReservationLifecycle->push(
                $selection['student_id'],
                DngPaymentRequest::query()->findOrFail($selection['reservation_id']),
                $selection['details'],
            );
        } catch (\Throwable $exception) {
            $this->settlementMutationGuard->handle($billingAccountId, function () use ($selection, $exception): void {
                $lockedInstallment = FinanceChargeInstallment::query()
                    ->lockForUpdate()
                    ->findOrFail($selection['installment_id']);

                $updates = [
                    'last_push_error' => $exception->getMessage(),
                    'last_push_attempted_at' => now(),
                    'push_attempt_count' => $lockedInstallment->push_attempt_count + 1,
                ];
                if (
                    $lockedInstallment->status === FinanceChargeInstallment::STATUS_AWAITING_PAYMENT
                    && (int) $lockedInstallment->dng_payment_request_id === $selection['reservation_id']
                ) {
                    $updates['status'] = FinanceChargeInstallment::STATUS_PENDING;
                }

                $lockedInstallment->update($updates);
            });

            throw $exception;
        }

        $fresh = FinanceChargeInstallment::query()->findOrFail($selection['installment_id']);

        Log::info('Installment pushed to DNG', [
            'finance_charge_id' => $selection['charge_id'],
            'installment_id' => $fresh->id,
            'installment_no' => $fresh->installment_no,
            'amount' => $fresh->amount,
        ]);

        InstallmentPushed::dispatch($fresh);

        return $fresh;
    }

    /**
     * Resolve which installment to push:
     * - Always pick the first not-yet-settled installment in sequence order.
     * - A later pending installment is not eligible while an earlier one awaits payment.
     * - An explicit id may only name that same next eligible installment.
     */
    private function resolveTarget(FinanceCharge $charge, ?int $explicitId): ?FinanceChargeInstallment
    {
        $next = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->orderBy('installment_no')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->first(fn (FinanceChargeInstallment $installment): bool => in_array(
                $installment->status,
                [
                    FinanceChargeInstallment::STATUS_PENDING,
                    FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                ],
                true,
            ));

        if ($next === null) {
            return null;
        }

        if ($explicitId !== null && $next->id !== $explicitId) {
            throw new \RuntimeException(
                "Installment #{$explicitId} is not the next eligible installment for charge #{$charge->id}."
            );
        }

        if ($next->status !== FinanceChargeInstallment::STATUS_PENDING) {
            if ($explicitId !== null) {
                throw new \RuntimeException(
                    "Installment #{$explicitId} is not pending (status={$next->status}). Cannot retry push."
                );
            }

            return null;
        }

        return $next;
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
