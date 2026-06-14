<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Jobs\ProcessDngWebhookJob;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use Illuminate\Database\QueryException;
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
        $payloadHash = DngWebhookEvent::computePayloadHash($payload);

        // FIN-17 / DB-04: deduplicate exact retries of the same call. A byte-
        // identical payload yields the same payload_hash, so an existing row means
        // DNG re-sent the same callback. Call 1 and Call 2 differ in payload (and
        // therefore hash), so this never collapses the two-call settle/invoice pair.
        $existing = DngWebhookEvent::query()->where('payload_hash', $payloadHash)->first();

        if ($existing !== null) {
            return $this->handleDuplicate($existing, $payload, $payloadHash);
        }

        $dngRequest = $this->resolveDngPaymentRequest($payload);
        $eventType = DngWebhookEvent::resolveEventType($payload);

        Log::info('DNG webhook: raw payload captured', [
            'payment_id' => $payload['PaymentId'] ?? null,
            'student_id' => $payload['StudentId'] ?? null,
            'item_id' => $payload['ItemId'] ?? null,
            'resolved_request_id' => $dngRequest?->id,
            'resolved_dng_payment_id' => $dngRequest?->dng_payment_id,
            'payload' => $payload,
        ]);

        try {
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
        } catch (QueryException $e) {
            // Race: a concurrent identical webhook inserted first and the
            // unique(payload_hash) guard rejected this one. Re-resolve and route it
            // through the same duplicate handling. Rethrow if it was a different error.
            $raced = DngWebhookEvent::query()->where('payload_hash', $payloadHash)->first();
            if ($raced !== null) {
                return $this->handleDuplicate($raced, $payload, $payloadHash);
            }

            throw $e;
        }

        Log::info('DNG webhook: inbox event stored', [
            'event_id' => $event->id,
            'payment_id' => $payload['PaymentId'] ?? null,
            'resolved_request_id' => $dngRequest?->id,
            'event_type' => $eventType,
        ]);

        ProcessDngWebhookJob::dispatch($event->id);

        return $this->acceptedResponse();
    }

    /**
     * Handle an inbound callback whose payload_hash already exists.
     *
     * The dedup guard must drop noise WITHOUT swallowing a legitimate retry. DNG
     * re-sends a callback precisely because we have not acknowledged settlement, so
     * we only treat the retry as a no-op when the prior attempt reached a STABLE
     * terminal outcome that an identical payload would simply reproduce:
     *   - processed  → already settled (idempotent)
     *   - mismatch   → deterministic checksum/business mismatch for this exact payload
     *   - skipped    → superseded/cancelled; identical payload skips again
     *
     * For any non-stable state (received / processing / failed_retryable) or a
     * failed_terminal — e.g. the matching request had not been created yet when the
     * first attempt ran (NOT_FOUND) — the retry must get another processing pass, so
     * we re-drive the EXISTING event (no duplicate row) instead of dropping it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleDuplicate(DngWebhookEvent $existing, array $payload, string $payloadHash): JsonResponse
    {
        $stableTerminal = [
            DngWebhookEvent::STATUS_PROCESSED,
            DngWebhookEvent::STATUS_MISMATCH,
            DngWebhookEvent::STATUS_SKIPPED,
        ];

        if (in_array($existing->processing_status, $stableTerminal, true)) {
            Log::info('DNG webhook: duplicate of stable terminal event skipped', [
                'payload_hash' => $payloadHash,
                'existing_event_id' => $existing->id,
                'existing_status' => $existing->processing_status,
                'payment_id' => $payload['PaymentId'] ?? null,
            ]);

            return $this->acceptedResponse();
        }

        // Reset out of job-terminal / backoff states so ProcessDngWebhookJob will not
        // early-return on the re-dispatch. A row stuck in `processing` is left alone
        // (its in-flight job owns it; a crashed one falls to failed_terminal and is
        // recovered by a later retry).
        if (in_array($existing->processing_status, [
            DngWebhookEvent::STATUS_FAILED_TERMINAL,
            DngWebhookEvent::STATUS_FAILED_RETRYABLE,
        ], true)) {
            $existing->markReceived();
        }

        if ($existing->processing_status !== DngWebhookEvent::STATUS_PROCESSING) {
            ProcessDngWebhookJob::dispatch($existing->id);
        }

        Log::info('DNG webhook: duplicate of non-terminal event re-dispatched', [
            'payload_hash' => $payloadHash,
            'existing_event_id' => $existing->id,
            'existing_status' => $existing->processing_status,
            'payment_id' => $payload['PaymentId'] ?? null,
        ]);

        return $this->acceptedResponse();
    }

    private function acceptedResponse(): JsonResponse
    {
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
        $campusCode = (string) ($payload['CampusCode'] ?? '');

        // Scope the inbox-link lookup by campus when present so two requests sharing
        // ItemId+StudentId across campuses don't link to the wrong row (matches the
        // service resolver and the (campus_code, item_id, student_code) index).
        if ($itemId !== '' && $studentId !== '') {
            $query = DngPaymentRequest::query()
                ->where('item_id', $itemId)
                ->where('student_code', $studentId);

            if ($campusCode !== '') {
                $query->where('campus_code', $campusCode);
            }

            $request = $query->first();

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
