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
            $event->markFailed('Missing PaymentId in payload');

            return;
        }

        // Find the matching DNG payment request
        $request = DngPaymentRequest::where('dng_payment_id', $dngPaymentId)->first();

        if (! $request) {
            // Try fallback: match by item_id + student_code
            $request = DngPaymentRequest::where('item_id', $payload['ItemId'] ?? '')
                ->where('student_code', $payload['StudentId'] ?? '')
                ->whereNull('dng_payment_id')
                ->first();

            // If found via fallback, set the dng_payment_id
            if ($request) {
                $request->update(['dng_payment_id' => $dngPaymentId]);
            }
        }

        if (! $request) {
            $event->markMismatch("No DNG payment request found for PaymentId: {$dngPaymentId}");
            Log::warning('DNG webhook: orphan callback', [
                'dng_payment_id' => $dngPaymentId,
                'event_id' => $event->id,
            ]);

            return;
        }

        // Link event to request
        $event->update(['dng_payment_request_id' => $request->id]);

        // Cross-validate amount and student
        if (! $this->crossValidate($request, $payload, $event)) {
            return;
        }

        // Determine target state based on event type
        $eventType = $event->event_type;
        $targetStatus = $eventType === DngWebhookEvent::EVENT_PAYMENT_INVOICED
            ? DngPaymentRequest::STATUS_PAID_INVOICED
            : DngPaymentRequest::STATUS_PAID_UNINVOICED;

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
        if ($request->canTransitionTo($targetStatus)) {
            $request->transitionTo($targetStatus);
        }

        // Bridge to canonical Payment if not yet done
        $this->dngPaymentService->bridgeToPayment($request);

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
        $issues = [];

        // Amount mismatch check
        $callbackAmount = (float) ($payload['Amount'] ?? 0);
        if (abs($callbackAmount - (float) $request->amount) > 0.01) {
            $issues[] = "Amount mismatch: local={$request->amount}, callback={$callbackAmount}";
        }

        // Student mismatch check
        $callbackStudentCode = $payload['StudentId'] ?? '';
        if ($callbackStudentCode && $callbackStudentCode !== $request->student_code) {
            $issues[] = "Student mismatch: local={$request->student_code}, callback={$callbackStudentCode}";
        }

        if (! empty($issues)) {
            $reason = implode('; ', $issues);
            $event->markMismatch($reason);
            Log::warning('DNG webhook: data mismatch', [
                'event_id' => $event->id,
                'dng_payment_request_id' => $request->id,
                'issues' => $issues,
            ]);

            return false;
        }

        return true;
    }
}
