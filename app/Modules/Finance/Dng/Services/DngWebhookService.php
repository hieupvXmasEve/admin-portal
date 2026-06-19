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
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
        protected ExamResitAttemptPaymentSyncer $examResitAttemptPaymentSyncer,
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

        // FIN-16: verify the checksum for EVERY mutation-capable event, including
        // the first "payment succeeded without invoice" callback (Call 1). DNG
        // signs Call 1 with an *empty* InvoiceSerialNumber segment, so it is fully
        // verifiable with the same formula (verifyWebhookChecksum already treats a
        // missing serial as ''). Previously Call 1 skipped checksum verification,
        // which left the public webhook boundary forgeable for settlement events.
        $isValidChecksum = $this->checksumService->verifyWebhookChecksum($request, $payload);
        $event->update(['is_valid_checksum' => $isValidChecksum]);

        if (! $isValidChecksum) {
            $event->markMismatch('Invalid checksum', DngWebhookEvent::ERROR_CATEGORY_CHECKSUM);

            return;
        }

        // Cross-validate amount and student BEFORE mutating the request. Binding the
        // callback PaymentId to a request that turns out to be a business mismatch
        // would poison it with a wrong dng_payment_id, so the bind is deferred into
        // the locked transition below and only happens once validation has passed.
        if (! $this->crossValidate($request, $payload, $event)) {
            return;
        }

        // Determine target state based on event type
        $eventType = $event->event_type;
        $targetStatus = $eventType === DngWebhookEvent::EVENT_PAYMENT_INVOICED
            ? DngPaymentRequest::STATUS_PAID_INVOICED
            : DngPaymentRequest::STATUS_PAID_UNINVOICED;

        // Decide and apply the status transition under a row lock, re-reading the
        // FRESH status inside the lock. Without this, concurrent callbacks (Call 1 vs
        // Call 2, or webhook vs reconciliation) can both read the same stale status
        // and a late Call 1 could downgrade a request that Call 2 already advanced
        // (e.g. paid_invoiced -> paid_uninvoiced). The lock serialises the decision
        // so each transition is computed against the committed current status.
        $outcome = DB::transaction(function () use ($request, $payload, $eventType, $targetStatus, $dngPaymentId): array {
            /** @var DngPaymentRequest $locked */
            $locked = DngPaymentRequest::query()->lockForUpdate()->find($request->id);

            // FIN-18: both local cancellation (STATUS_CANCELLED) and DNG-pushed
            // cancellation (STATUS_CANCEL_PUSHED_TO_DNG) are terminal. A late callback
            // for either must never revive or bridge a cancelled request.
            if (in_array($locked->status, [
                DngPaymentRequest::STATUS_CANCELLED,
                DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG,
            ], true)) {
                return ['result' => 'cancelled'];
            }

            // Bind the callback PaymentId now that checksum + business validation have
            // passed (deferred from before crossValidate so a mismatched callback can
            // never poison the request with a wrong dng_payment_id).
            if (! $locked->dng_payment_id) {
                $locked->update(['dng_payment_id' => $dngPaymentId]);
            }

            $currentOrder = $this->statusOrder($locked->status);
            $targetOrder = $this->statusOrder($targetStatus);

            if ($currentOrder > $targetOrder) {
                return ['result' => 'already_progressed'];
            }

            if ($currentOrder === $targetOrder) {
                return ['result' => 'equivalent'];
            }

            // Advance: refuse an illegal transition BEFORE mutating any fields, so a
            // request that cannot legally reach the target (e.g. a failed request) is
            // never partially updated.
            if (! $locked->canTransitionTo($targetStatus)) {
                return ['result' => 'cannot_transition', 'from' => $locked->status];
            }

            // Is this the FIRST time the request reaches a paid state? Call 1
            // (pushed_to_dng -> paid_uninvoiced) and an out-of-order Call 2
            // (pushed_to_dng -> paid_invoiced) are first settlements. Call 2 after
            // Call 1 (paid_uninvoiced -> paid_invoiced) only attaches invoice
            // metadata and must NOT re-run payment-received side effects.
            $isFirstSettlement = $this->statusOrder($locked->status)
                < $this->statusOrder(DngPaymentRequest::STATUS_PAID_UNINVOICED);

            $updateData = [
                'last_callback_payload' => $payload,
                'psp_code' => $payload['PSPCode'] ?? $locked->psp_code,
            ];

            if (! $locked->paid_at) {
                $updateData['paid_at'] = now();
            }

            if ($eventType === DngWebhookEvent::EVENT_PAYMENT_INVOICED) {
                $updateData['invoice_serial_number'] = $payload['InvoiceSerialNumber'];
                $updateData['invoice_date'] = $payload['InvoiceDate'];
            }

            $locked->update($updateData);
            $locked->transitionTo($targetStatus);

            return ['result' => 'advanced', 'first_settlement' => $isFirstSettlement];
        });

        switch ($outcome['result']) {
            case 'cancelled':
                $event->markSkipped('Request was superseded and cancelled before this callback arrived');

                return;

            case 'already_progressed':
                $this->recoverPaidState($request->fresh());
                $event->markSkipped('Request already progressed beyond this event');

                return;

            case 'equivalent':
                $this->recoverPaidState($request->fresh());
                $event->markSkipped('Equivalent event already applied');

                return;

            case 'cannot_transition':
                $event->markFailedTerminal(
                    "Cannot transition request from {$outcome['from']} to {$targetStatus}",
                    DngWebhookEvent::ERROR_CATEGORY_PROCESSING,
                );

                return;
        }

        // result === 'advanced'
        $freshRequest = $request->fresh();

        // The Payment bridge is idempotent and always ensured (Call 2 arriving first
        // still needs the Payment created).
        $this->ensurePaymentBridge($freshRequest);

        if ($outcome['first_settlement']) {
            // Mark linked installment as paid + dispatch next push (post-commit).
            // No-op if the DNG request has no linked installment (legacy / non-installment flow).
            $this->settleInstallmentAction->handle($freshRequest);

            // Auto-enroll retake course registrations when payment confirmed.
            $this->handleRetakeCourseAutoEnroll($freshRequest);

            // Sync exam-resit (thi lại) HQ paid state from canonical Finance evidence.
            $this->handleExamResitPaymentSync($freshRequest);
        }

        $event->markProcessed();

        if ($outcome['first_settlement']) {
            // Notify "payment received" exactly once — only on the first paid
            // transition. Call 2 (invoice attach) must not re-notify the student.
            $this->publishPaymentReceivedNotification($freshRequest->fresh());
        }

        Log::info('DNG webhook processed', [
            'event_id' => $event->id,
            'dng_payment_request_id' => $request->id,
            'event_type' => $eventType,
            'first_settlement' => $outcome['first_settlement'],
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
        $campusCode = (string) ($payload['CampusCode'] ?? '');

        // ItemId + StudentId + Campus is the stable correlation key. Scope by campus
        // when the callback carries it so two requests sharing ItemId+StudentId across
        // campuses don't link to the wrong row (matches the schema index and the
        // reconciliation matcher). PaymentId is only a fallback when no key match.
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
     * Recovery for a late/duplicate callback on an already-advanced request: ensure
     * BOTH the canonical Payment exists AND the linked installment is settled. A prior
     * attempt may have advanced the status but crashed before completing the bridge or
     * the installment settlement, which would otherwise leave the next installment
     * un-pushed forever. Both operations are idempotent (bridge guards on payment_id;
     * SettleInstallmentFromDngAction locks rows and skips already-paid installments).
     */
    private function recoverPaidState(DngPaymentRequest $request): void
    {
        $this->ensurePaymentBridge($request);
        $this->settleInstallmentAction->handle($request);
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

    /**
     * Sync exam-resit (thi lại) HQ paid state after payment confirmation.
     *
     * Only applicable to PTL fee DNG requests. Payment bridge + settlement truth
     * identify every linked exam_resit_fee charge that is fully paid, so the syncer
     * flips the matching ExamResitAttempt to paid from canonical Finance evidence.
     */
    private function handleExamResitPaymentSync(DngPaymentRequest $request): void
    {
        try {
            if ($request->fee_type !== 'PTL') {
                return;
            }

            $this->examResitAttemptPaymentSyncer->runForStudent((int) $request->student_id);
        } catch (\Throwable $e) {
            Log::warning('DNG webhook: exam resit payment sync failed', [
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
            // FIN-18: cancel pushed to DNG is terminal too; without this it fell
            // through to default => 0 and a late callback could be processed.
            DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG => -2,
            default => 0,
        };
    }
}
