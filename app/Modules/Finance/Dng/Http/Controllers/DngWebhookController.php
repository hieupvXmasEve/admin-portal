<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DngWebhookController extends Controller
{
    /**
     * Handle DNG payment callback.
     * Persist raw inbound payload first, then process asynchronously.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $dngRequest = $this->resolveDngPaymentRequest($payload);
        $eventType = DngWebhookEvent::resolveEventType($payload);
        $payloadHash = DngWebhookEvent::computePayloadHash($payload);

        Log::info('DNG webhook: raw payload captured', [
            'payment_id' => $payload['PaymentId'] ?? null,
            'student_id' => $payload['StudentId'] ?? null,
            'item_id' => $payload['ItemId'] ?? null,
            'resolved_request_id' => $dngRequest?->id,
            'resolved_dng_payment_id' => $dngRequest?->dng_payment_id,
            'payload' => $payload,
        ]);

        $event = DngWebhookEvent::create([
            'dng_payment_id' => $payload['PaymentId'] ?? null,
            'event_type' => $eventType,
            'payload_hash' => $payloadHash,
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'dng_payment_request_id' => $dngRequest?->id,
            'is_valid_checksum' => false,
            'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
            'error_message' => null,
            'received_at' => now(),
            'attempt_count' => 0,
            'last_attempt_at' => null,
            'next_retry_at' => null,
            'error_category' => null,
        ]);

        Log::info('DNG webhook: inbox event stored', [
            'event_id' => $event->id,
            'payment_id' => $payload['PaymentId'] ?? null,
            'resolved_request_id' => $dngRequest?->id,
            'event_type' => $eventType,
        ]);

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
        $itemId = (string) ($payload['ItemId'] ?? '');
        $studentId = (string) ($payload['StudentId'] ?? '');

        if ($itemId !== '' && $studentId !== '') {
            $request = DngPaymentRequest::query()
                ->where('item_id', $itemId)
                ->where('student_code', $studentId)
                ->first();

            if ($request) {
                return $request;
            }
        }

        $paymentId = $payload['PaymentId'] ?? null;
        $request = null;

        if (is_string($paymentId) && $paymentId !== '') {
            $request = DngPaymentRequest::query()
                ->where('dng_payment_id', $paymentId)
                ->orWhere('dng_transaction_id', $paymentId)
                ->first();
        }

        if ($request) {
            return $request;
        }

        return null;
    }
}
