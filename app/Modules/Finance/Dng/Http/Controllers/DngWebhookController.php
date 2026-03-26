<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
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

        // Verify checksum
        // Note: exact checksum string for callbacks must be confirmed with DNG.
        $checksumValue = ($payload['CampusCode'] ?? '')
            .($payload['StudentId'] ?? '')
            .($payload['PaymentId'] ?? '')
            .(string) ($payload['Amount'] ?? '');

        $isValidChecksum = $this->checksumService->verify(
            $checksumValue,
            $payload['CheckSum'] ?? '',
        );

        // Determine event type
        $eventType = DngWebhookEvent::resolveEventType($payload);

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
}
