<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\FinanceCharge;
use App\Modules\Academic\Actions\AutoEnrollRetakeCourseAction;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DngWebhookService
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngChecksumService $checksumService,
        protected PublishDomainEventAction $publishDomainEventAction,
        protected SettleInstallmentFromDngAction $settleInstallmentAction,
        protected RetakeRegistrationPaymentSyncer $retakeRegistrationPaymentSyncer,
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

        if ($request->status === DngPaymentRequest::STATUS_CANCELLED) {
            $event->markSkipped('Request was superseded and cancelled before this callback arrived');

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

        $freshRequest = $request->fresh();

        $this->ensurePaymentBridge($freshRequest);

        // Mark linked installment as paid + dispatch next push (post-commit).
        // No-op if the DNG request has no linked installment (legacy / non-installment flow).
        $this->settleInstallmentAction->handle($freshRequest);

        // Auto-enroll retake course registrations when payment confirmed
        $this->handleRetakeCourseAutoEnroll($freshRequest);

        $event->markProcessed();

        $this->publishPaymentReceivedNotification($freshRequest->fresh());

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

    /**
     * Handle auto-enrollment for retake course registrations after payment confirmation.
     *
     * Resolution order:
     * 1. chargeLinks pivot (aggregate request covering N charges — one per unit)
     * 2. finance_charge_id (single-charge precise link — legacy single-unit requests)
     * 3. Legacy fallback: latest active retake_fee charge (old requests before finance_charge_id column)
     */
    private function handleRetakeCourseAutoEnroll(DngPaymentRequest $request): void
    {
        try {
            // Only applicable to retake fee DNG requests
            if ($request->fee_type !== 'HL') {
                return;
            }

            // Priority 1: pivot chargeLinks — aggregate request covers multiple charges
            $chargeLinks = $request->chargeLinks()->get();
            if ($chargeLinks->isNotEmpty()) {
                foreach ($chargeLinks as $link) {
                    $charge = FinanceCharge::find($link->finance_charge_id);
                    if ($charge) {
                        AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);
                    }
                }
            } else {
                // Priority 2: single precise charge link (single-unit DNG request)
                $charge = $request->finance_charge_id
                    ? FinanceCharge::find($request->finance_charge_id)
                    : null;

                if ($charge) {
                    AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);
                }
            }

            // Legacy aggregate requests may not have pivot links. After payment bridge,
            // settlement truth identifies every linked retake charge that is fully paid.
            $this->retakeRegistrationPaymentSyncer->runForStudent((int) $request->student_id);
        } catch (\Throwable $e) {
            Log::warning('DNG webhook: retake course auto-enroll failed', [
                'dng_payment_request_id' => $request->id,
                'finance_charge_id' => $request->finance_charge_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function publishPaymentReceivedNotification(DngPaymentRequest $request): void
    {
        try {
            $student = $request->student;
            $studentName = $student?->full_name ?? '';
            $formattedAmount = number_format((float) $request->amount, 0, ',', '.').' VNĐ';

            $envelope = new DomainEventEnvelope(
                eventId: (string) Str::uuid(),
                eventName: 'finance.dng_payment_received',
                eventVersion: 1,
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'dng_payment_request',
                aggregateId: (string) $request->id,
                campusId: $student?->campus_id ? (int) $student->campus_id : null,
                actorUserId: null,
                payload: [
                    'type_key' => 'dng_payment_received',
                    'channels' => ['realtime', 'email'],
                    'recipient_targets' => [
                        ['type' => 'student', 'id' => $request->student_id],
                    ],
                    'data' => [
                        'title' => 'Xác nhận thanh toán thành công',
                        'body' => "Hệ thống đã nhận được khoản thanh toán {$formattedAmount} của bạn.",
                        'category' => 'finance',
                        'is_important' => true,
                        'dng_payment_request_id' => $request->id,
                        'student_name' => $studentName,
                        'student_code' => $request->student_code,
                        'amount_formatted' => $formattedAmount,
                        'semester_code' => $request->semester?->code ?? '',
                        'paid_at' => $request->paid_at?->toISOString(),
                    ],
                ],
            );

            $this->publishDomainEventAction->run($envelope);
        } catch (\Throwable $e) {
            Log::warning('Failed to publish DNG payment received notification', [
                'dng_payment_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function statusOrder(string $status): int
    {
        return match ($status) {
            DngPaymentRequest::STATUS_PENDING => 0,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG => 1,
            DngPaymentRequest::STATUS_PAID_UNINVOICED => 2,
            DngPaymentRequest::STATUS_PAID_INVOICED => 3,
            DngPaymentRequest::STATUS_RECONCILED => 4,
            DngPaymentRequest::STATUS_FAILED => -1,
            DngPaymentRequest::STATUS_CANCELLED => -2,
            default => 0,
        };
    }
}
