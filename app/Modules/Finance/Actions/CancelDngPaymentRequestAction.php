<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelDngPaymentRequestAction
{
    public function __construct(
        protected DngClient $dngClient,
        protected VoidFinanceChargeAction $voidChargeAction,
    ) {}

    /**
     * Cancel a DNG payment request and void any linked charges + retake registrations.
     *
     * - For `pending` requests: transition directly to `cancelled` (never reached DNG).
     * - For `pushed_to_dng` requests: call DNG API with amount=-1 first; only transition
     *   to `cancel_pushed_to_dng` on success. Throws on DNG API failure.
     *
     * After the DNG status is settled, all linked FinanceCharges are voided and any
     * CourseRetakeRegistrations linked to those charges are cancelled.
     *
     * @throws \RuntimeException if the request status cannot be cancelled.
     * @throws \Throwable if the DNG API call fails for a pushed_to_dng request.
     */
    public function run(DngPaymentRequest $request): void
    {
        if ($request->status === DngPaymentRequest::STATUS_PENDING) {
            DB::transaction(function () use ($request): void {
                $request->transitionTo(DngPaymentRequest::STATUS_CANCELLED);
                $this->voidLinkedChargesAndRegistrations($request);
            });

            return;
        }

        if ($request->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
            $this->cancelPushedRequest($request);

            return;
        }

        throw new \RuntimeException(
            "Cannot cancel DNG payment request #{$request->id}: current status '{$request->status}' does not allow cancellation."
        );
    }

    /**
     * Cancel a request that was already pushed to DNG by calling the DNG API with amount=-1.
     * Stores cancel payload/response for audit. Does NOT save on DNG API failure.
     * On DNG API success, voids linked charges and cancels retake registrations.
     */
    private function cancelPushedRequest(DngPaymentRequest $request): void
    {
        // Reconstruct the original data fields needed for the cancel call.
        // Student-specific fields (name, email, etc.) are recovered from the stored push_payload
        // since the model itself only stores student_code / campus_code / fee_type / item_id.
        $pushPayload = $request->push_payload ?? [];

        $originalData = [
            'student_code' => $request->student_code,
            'campus_code' => $request->campus_code,
            'type' => $request->fee_type,
            'item_id' => $request->item_id,
            'student_name' => $pushPayload['StudentName'] ?? '',
            'email' => $pushPayload['Email'] ?? '',
            'estimate_time' => $pushPayload['EstimateTime'] ?? '',
            'student_address' => $pushPayload['StudentAddress'] ?? '',
            'cccd' => $pushPayload['CCCD'] ?? null,
        ];

        // Build the cancel payload before calling so we can store it regardless of outcome.
        $cancelPayload = $this->dngClient->buildInsertNewRecordPayload(
            array_merge($originalData, ['amount' => -1])
        );

        try {
            $response = $this->dngClient->cancelRecord($originalData);
        } catch (\Throwable $e) {
            Log::error('DNG cancel call failed', [
                'dng_payment_request_id' => $request->id,
                'error' => $e->getMessage(),
                'cancel_payload' => $cancelPayload,
            ]);

            // Store the attempted payload for audit but do NOT change the status.
            $request->update(['cancel_push_payload' => $cancelPayload]);

            throw $e;
        }

        // DNG API succeeded — persist status + clean up linked data atomically.
        DB::transaction(function () use ($request, $cancelPayload, $response): void {
            $request->update([
                'cancel_push_payload' => $cancelPayload,
                'cancel_push_response' => $response,
            ]);

            $request->transitionTo(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG);

            $this->voidLinkedChargesAndRegistrations($request);
        });
    }

    /**
     * Collect all FinanceCharges linked to this DNG request (via chargeLinks pivot or
     * single finance_charge_id), void each one, and cancel any associated
     * CourseRetakeRegistrations.
     */
    private function voidLinkedChargesAndRegistrations(DngPaymentRequest $request): void
    {
        $charges = $this->resolveLinkedCharges($request);

        if ($charges->isEmpty()) {
            return;
        }

        $userId = auth()->id();
        $reason = "DNG payment request #{$request->id} cancelled";

        foreach ($charges as $charge) {
            if ($charge->status === FinanceCharge::STATUS_VOID) {
                Log::info('Skipping void for already-voided charge during DNG cancel', [
                    'dng_payment_request_id' => $request->id,
                    'finance_charge_id' => $charge->id,
                ]);

                continue;
            }

            // Cancel retake registration first (before void, to avoid mutation conflicts)
            $this->cancelRetakeRegistration($charge, $userId, $reason);

            try {
                $this->voidChargeAction->handle($charge->id, $reason, $userId, autoReallocate: false);
            } catch (\Throwable $e) {
                Log::error('Failed to void charge after DNG cancel', [
                    'dng_payment_request_id' => $request->id,
                    'finance_charge_id' => $charge->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

    /**
     * Resolve the FinanceCharges linked to this DNG request.
     *
     * Priority:
     *  1. chargeLinks pivot (multi-charge aggregate requests)
     *  2. direct finance_charge_id (single-charge legacy)
     *
     * @return Collection<int, FinanceCharge>
     */
    private function resolveLinkedCharges(DngPaymentRequest $request): Collection
    {
        $request->loadMissing(['chargeLinks.financeCharge']);

        $pivotCharges = $request->chargeLinks
            ->map(fn ($link) => $link->financeCharge)
            ->filter()
            ->values();

        if ($pivotCharges->isNotEmpty()) {
            return $pivotCharges;
        }

        if ($request->finance_charge_id !== null) {
            $charge = FinanceCharge::find($request->finance_charge_id);

            return $charge ? collect([$charge]) : collect();
        }

        return collect();
    }

    /**
     * Cancel the CourseRetakeRegistration linked to the given charge (if any).
     * Only cancels non-terminal registrations.
     */
    private function cancelRetakeRegistration(FinanceCharge $charge, ?int $userId, string $reason): void
    {
        if ($charge->source_type !== CourseRetakeRegistration::class || $charge->source_id === null) {
            return;
        }

        $registration = CourseRetakeRegistration::find($charge->source_id);

        if ($registration === null) {
            return;
        }

        if (! in_array($registration->status, CourseRetakeRegistration::CANCELLABLE_STATUSES, true)) {
            return;
        }

        $registration->cancel($userId ?? 0, $reason);
    }
}
