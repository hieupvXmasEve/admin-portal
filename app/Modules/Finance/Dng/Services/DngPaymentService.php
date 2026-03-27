<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\Payment;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DngPaymentService
{
    public function __construct(
        protected DngClient $dngClient,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Create a local DNG payment request and push debt to DNG.
     *
     * @param  array{
     *     campus_code: string,
     *     student_code: string,
     *     fee_type: string,
     *     item_id: string,
     *     amount: float|int|string,
     *     type: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     cccd?: string|null,
     * }  $chargeData
     */
    public function createAndPush(Student $student, array $chargeData): DngPaymentRequest
    {
        // Step 1: Create local record first (must commit before calling DNG)
        $request = DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => $chargeData['campus_code'],
            'student_code' => $chargeData['student_code'],
            'fee_type' => $chargeData['fee_type'],
            'item_id' => $chargeData['item_id'],
            'amount' => $chargeData['amount'],
            'status' => DngPaymentRequest::STATUS_PENDING,
        ]);

        // Step 2: Push to DNG
        $pushPayload = $this->dngClient->buildInsertNewRecordPayload($chargeData);

        try {
            $response = $this->dngClient->insertNewRecord($chargeData, $pushPayload);

            $request->update([
                'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                'push_payload' => $pushPayload,
                'push_response' => $response,
                'dng_transaction_id' => $response['data']['TransactionID'] ?? $response['data']['Id'] ?? null,
                'dng_payment_id' => $response['data']['PaymentId'] ?? $response['data']['OtherId'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $request->update([
                'status' => DngPaymentRequest::STATUS_FAILED,
                'push_payload' => $pushPayload,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('DNG push debt failed', [
                'dng_payment_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $request->refresh();
    }

    /**
     * Pull QR code / virtual account from DNG.
     *
     * @param  array<int, string>  $feeTypes
     * @return array<string, mixed> QR data from DNG
     */
    public function pullQrCode(DngPaymentRequest $request, array $feeTypes): array
    {
        $response = $this->dngClient->createVirtualAccountByFeeType([
            'student_code' => $request->student_code,
            'campus_code' => $request->campus_code,
            'fee_types' => $feeTypes,
        ]);

        $request->update([
            'qr_payload' => $response,
        ]);

        // Only transition if currently in pushed_to_dng state
        if ($request->canTransitionTo(DngPaymentRequest::STATUS_QR_READY)) {
            $request->transitionTo(DngPaymentRequest::STATUS_QR_READY);
        }

        return $response;
    }

    /**
     * Bridge a confirmed DNG payment into the canonical Payment system.
     * Called when a callback confirms the student has paid.
     *
     * Guard: skip if payment_id already set (idempotent).
     */
    public function bridgeToPayment(DngPaymentRequest $request): ?Payment
    {
        if ($request->hasBridgedPayment()) {
            return $request->payment;
        }

        return DB::transaction(function () use ($request) {
            // Lock row to prevent concurrent bridge (webhook + reconciliation race)
            $request = DngPaymentRequest::lockForUpdate()->find($request->id);
            if ($request->hasBridgedPayment()) {
                return $request->payment;
            }

            $payment = $this->paymentService->recordPayment([
                'student_id' => $request->student_id,
                'amount' => $request->amount,
                'method' => Payment::METHOD_GATEWAY,
                'source' => 'dng',
                'external_ref' => $request->dng_payment_id,
                'paid_at' => $request->paid_at ?? now(),
                'status' => Payment::STATUS_COMPLETED,
                'raw_payload' => $request->last_callback_payload,
                'received_by_user_id' => null,
            ]);

            $request->update(['payment_id' => $payment->id]);

            // Auto-allocate the payment to outstanding charges
            $this->paymentService->autoAllocatePayment($payment->id);

            Log::info('DNG payment bridged to canonical Payment', [
                'dng_payment_request_id' => $request->id,
                'payment_id' => $payment->id,
                'amount' => $request->amount,
            ]);

            return $payment;
        });
    }
}
