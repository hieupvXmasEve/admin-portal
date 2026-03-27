<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use Illuminate\Support\Facades\Log;

class DngWebhookService
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngChecksumService $checksumService,
    ) {}

    /**
     * Process a webhook event: find the DNG payment request,
     * validate data, transition state, and bridge to Payment if confirmed.
     */
    public function processEvent(DngWebhookEvent $event): void
    {
        $payload = $event->payload;
        $dngPaymentId = $payload['PaymentId'] ?? null;

        if (! $dngPaymentId) {
            $event->markFailedTerminal('Missing PaymentId in payload', DngWebhookEvent::ERROR_CATEGORY_MALFORMED);

            return;
        }

        $request = $this->resolvePaymentRequest($payload, $dngPaymentId);

        if (! $request) {
            $event->markFailedTerminal(
                "No DNG payment request found for PaymentId: {$dngPaymentId}",
                DngWebhookEvent::ERROR_CATEGORY_NOT_FOUND,
            );
            Log::warning('DNG webhook: orphan callback', [
                'dng_payment_id' => $dngPaymentId,
                'event_id' => $event->id,
            ]);

            return;
        }

        // Link event to request
        $event->update(['dng_payment_request_id' => $request->id]);

        $shouldVerifyChecksum = filled($payload['InvoiceSerialNumber'] ?? null);
        $isValidChecksum = ! $shouldVerifyChecksum
            || $this->checksumService->verifyWebhookChecksum($request, $payload);
        $event->update(['is_valid_checksum' => $isValidChecksum]);

        if ($shouldVerifyChecksum && ! $isValidChecksum) {
            $event->markMismatch('Invalid checksum', DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);

            return;
        }

        if (! $request->dng_payment_id) {
            $request->update(['dng_payment_id' => $dngPaymentId]);
            $request = $request->fresh();
        }

        // Cross-validate amount and student
        if (! $this->crossValidate($request, $payload, $event)) {
            return;
        }

        // Determine target state based on event type
        $eventType = $event->event_type;
        $targetStatus = $eventType === DngWebhookEvent::EVENT_PAYMENT_INVOICED
            ? DngPaymentRequest::STATUS_PAID_INVOICED
            : DngPaymentRequest::STATUS_PAID_UNINVOICED;

        if ($this->statusOrder($request->status) > $this->statusOrder($targetStatus)) {
            $this->ensurePaymentBridge($request);
            $event->markSkipped('Request already progressed beyond this event');

            return;
        }

        if ($this->statusOrder($request->status) === $this->statusOrder($targetStatus)) {
            $this->ensurePaymentBridge($request);
            $event->markSkipped('Equivalent event already applied');

            return;
        }

        // Update request fields from callback
        $updateData = [
            'last_callback_payload' => $payload,
            'psp_code' => $payload['PSPCode'] ?? $request->psp_code,
        ];

        if (! $request->paid_at) {
            $updateData['paid_at'] = now();
        }

        // Add invoice fields if present
        if ($eventType === DngWebhookEvent::EVENT_PAYMENT_INVOICED) {
            $updateData['invoice_serial_number'] = $payload['InvoiceSerialNumber'];
            $updateData['invoice_date'] = $payload['InvoiceDate'];
        }

        $request->update($updateData);

        // Transition state (forward only)
        if (! $request->canTransitionTo($targetStatus)) {
            $event->markFailedTerminal(
                "Cannot transition request from {$request->status} to {$targetStatus}",
                DngWebhookEvent::ERROR_CATEGORY_PROCESSING,
            );

            return;
        }

        $request->transitionTo($targetStatus);

        $this->ensurePaymentBridge($request->fresh());

        $event->markProcessed();

        Log::info('DNG webhook processed', [
            'event_id' => $event->id,
            'dng_payment_request_id' => $request->id,
            'event_type' => $eventType,
            'new_status' => $request->fresh()->status,
        ]);
    }

    /**
     * Cross-validate callback data against local request.
     */
    private function crossValidate(
        DngPaymentRequest $request,
        array $payload,
        DngWebhookEvent $event,
    ): bool {
        $issues = $request->callbackMismatchReasons($payload);

        if (! empty($issues)) {
            $reason = implode('; ', $issues);
            $event->markMismatch($reason, DngWebhookEvent::ERROR_CATEGORY_MISMATCH);
            Log::warning('DNG webhook: data mismatch', [
                'event_id' => $event->id,
                'dng_payment_request_id' => $request->id,
                'issues' => $issues,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePaymentRequest(array $payload, string $dngPaymentId): ?DngPaymentRequest
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

        $request = DngPaymentRequest::query()
            ->where('dng_payment_id', $dngPaymentId)
            ->first();

        if ($request) {
            return $request;
        }

        $request = DngPaymentRequest::query()
            ->where('dng_transaction_id', $dngPaymentId)
            ->first();

        if ($request) {
            return $request;
        }

        return null;
    }

    private function ensurePaymentBridge(DngPaymentRequest $request): void
    {
        if (! $request->hasBridgedPayment()) {
            $this->dngPaymentService->bridgeToPayment($request);
        }
    }

    private function statusOrder(string $status): int
    {
        return match ($status) {
            DngPaymentRequest::STATUS_PENDING => 0,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG => 1,
            DngPaymentRequest::STATUS_QR_READY => 2,
            DngPaymentRequest::STATUS_PAID_UNINVOICED => 3,
            DngPaymentRequest::STATUS_PAID_INVOICED => 4,
            DngPaymentRequest::STATUS_RECONCILED => 5,
            DngPaymentRequest::STATUS_FAILED => -1,
            default => 0,
        };
    }
}
