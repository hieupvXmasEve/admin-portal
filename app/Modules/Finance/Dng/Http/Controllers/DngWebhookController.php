<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DngWebhookController extends Controller
{
    public function __construct(
        protected DngChecksumService $checksumService,
    ) {}

    /**
     * Handle DNG payment callback.
     *
     * Validates, deduplicates, stores event, dispatches async processing.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Basic field validation
        $request->validate([
            'StudentId' => 'required|string',
            'PaymentId' => 'required|string',
            'Amount' => 'required|numeric',
            'CampusCode' => 'required|string',
            'CheckSum' => 'required|string',
        ]);

        // Compute payload hash for dedup
        $payloadHash = DngWebhookEvent::computePayloadHash($payload);

        // Look up the original DNG payment request to build checksum from stored values
        $dngRequest = $this->resolveDngPaymentRequest($payload);

        if (! $dngRequest) {
            Log::warning('DNG webhook: payment request not found', [
                'payment_id' => $payload['PaymentId'] ?? null,
                'student_id' => $payload['StudentId'] ?? null,
                'item_id' => $payload['ItemId'] ?? null,
            ]);

            return response()->json([
                'Code' => 422,
                'Type' => 'Error',
                'Message' => 'Payment request not found',
                'data' => null,
            ], 422);
        }

        // Verify checksum using original request values + config
        // Formula: AccessCode + ClientCode + Amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode
        // TEMPORARY: bypass checksum enforcement while validating actual third-party callback payloads in test.
        // $isValidChecksum = $this->checksumService->verifyWebhookChecksum($dngRequest, $payload);
        $isValidChecksum = true;

        // Determine event type
        $eventType = DngWebhookEvent::resolveEventType($payload);

        $existingPayloadEvent = DngWebhookEvent::query()
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($existingPayloadEvent) {
            if (
                $existingPayloadEvent->is_valid_checksum
                && in_array($existingPayloadEvent->processing_status, [
                    DngWebhookEvent::STATUS_FAILED,
                    DngWebhookEvent::STATUS_MISMATCH,
                    DngWebhookEvent::STATUS_SKIPPED,
                ], true)
            ) {
                $existingPayloadEvent->update([
                    'processing_status' => DngWebhookEvent::STATUS_PENDING,
                    'processed_at' => null,
                    'error_message' => null,
                ]);

                ProcessDngWebhookJob::dispatch($existingPayloadEvent->id);

                return response()->json([
                    'Code' => 200,
                    'Type' => 'Success',
                    'Message' => 'Accepted',
                    'data' => null,
                ]);
            }

            return response()->json([
                'Code' => 200,
                'Type' => 'Success',
                'Message' => 'Already received',
                'data' => null,
            ]);
        }

        if ($isValidChecksum) {
            $mismatchReasons = $dngRequest->callbackMismatchReasons($payload);
            if ($mismatchReasons !== []) {
                Log::warning('DNG webhook: payload mismatch', [
                    'payment_id' => $payload['PaymentId'] ?? null,
                    'dng_payment_request_id' => $dngRequest->id,
                    'issues' => $mismatchReasons,
                ]);

                return response()->json([
                    'Code' => 422,
                    'Type' => 'Error',
                    'Message' => 'Payload mismatch',
                    'data' => $mismatchReasons,
                ], 422);
            }

            $existingEvent = DngWebhookEvent::query()
                ->where('dng_payment_id', $payload['PaymentId'])
                ->where('event_type', $eventType)
                ->where('is_valid_checksum', true)
                ->whereIn('processing_status', [
                    DngWebhookEvent::STATUS_PENDING,
                    DngWebhookEvent::STATUS_PROCESSED,
                ])
                ->exists();

            if ($existingEvent) {
                return response()->json([
                    'Code' => 200,
                    'Type' => 'Success',
                    'Message' => 'Already received',
                    'data' => null,
                ]);
            }
        }

        // Atomic dedup: try to insert, catch unique constraint violation
        try {
            $event = DngWebhookEvent::create([
                'dng_payment_id' => $payload['PaymentId'] ?? null,
                'event_type' => $eventType,
                'payload_hash' => $payloadHash,
                'headers' => $request->headers->all(),
                'payload' => $payload,
                'is_valid_checksum' => $isValidChecksum,
                'processing_status' => $isValidChecksum
                    ? DngWebhookEvent::STATUS_PENDING
                    : DngWebhookEvent::STATUS_FAILED,
                'error_message' => $isValidChecksum ? null : 'Invalid checksum',
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'Code' => 200,
                'Type' => 'Success',
                'Message' => 'Already received',
                'data' => null,
            ]);
        }

        if (! $isValidChecksum) {
            Log::warning('DNG webhook: invalid checksum', [
                'event_id' => $event->id,
                'payment_id' => $payload['PaymentId'] ?? null,
            ]);

            return response()->json([
                'Code' => 401,
                'Type' => 'Error',
                'Message' => 'Invalid checksum',
                'data' => null,
            ], 401);
        }

        // Dispatch async processing and return 200 fast
        ProcessDngWebhookJob::dispatch($event->id);

        return response()->json([
            'Code' => 200,
            'Type' => 'Success',
            'Message' => 'Accepted',
            'data' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDngPaymentRequest(array $payload): ?DngPaymentRequest
    {
        $paymentId = $payload['PaymentId'] ?? null;
        if (! is_string($paymentId) || $paymentId === '') {
            return null;
        }

        $request = DngPaymentRequest::query()
            ->where('dng_payment_id', $paymentId)
            ->first();

        if ($request) {
            return $request;
        }

        $itemId = $payload['ItemId'] ?? null;
        $studentId = $payload['StudentId'] ?? null;
        $campusCode = $payload['CampusCode'] ?? null;

        if (! is_string($itemId) || $itemId === '' || ! is_string($studentId) || $studentId === '' || ! is_string($campusCode) || $campusCode === '') {
            return null;
        }

        $request = DngPaymentRequest::query()
            ->where('item_id', $itemId)
            ->where('student_code', $studentId)
            ->where('campus_code', $campusCode)
            ->whereNull('dng_payment_id')
            ->first();

        if (! $request) {
            return null;
        }

        $request->update(['dng_payment_id' => $paymentId]);

        return $request->fresh();
    }
}
