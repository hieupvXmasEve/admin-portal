<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Domain\Contracts\NotificationIntent;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Policies\PolicyResolver;
use App\Modules\Notification\Support\EventIntentMapper;
use App\Modules\Notification\Support\NotificationAuditLogger;
use App\Modules\Notification\Support\NotificationMetrics;
use App\Modules\Notification\Support\RecipientResolver;
use Illuminate\Support\Facades\Log;

class HandleOutboxEventAction
{
    public function __construct(
        private readonly EventIntentMapper $intentMapper,
        private readonly PolicyResolver $policyResolver,
        private readonly RecipientResolver $recipientResolver,
        private readonly PersistIntentAction $persistIntentAction,
        private readonly NotificationAuditLogger $auditLogger,
        private readonly NotificationMetrics $metrics,
        private readonly EmailContentRegistry $emailContentRegistry,
    ) {}

    public function run(NotificationEventOutbox $outbox): int
    {
        $envelope = DomainEventEnvelope::fromArray([
            'event_id' => $outbox->event_id,
            'event_name' => $outbox->event_name,
            'event_version' => $outbox->event_version,
            'occurred_at' => $outbox->occurred_at,
            'aggregate_type' => $outbox->aggregate_type,
            'aggregate_id' => $outbox->aggregate_id,
            'campus_id' => $outbox->campus_id,
            'actor_user_id' => $outbox->actor_user_id,
            'payload' => $outbox->payload,
        ]);

        $intents = $this->intentMapper->map($envelope);
        if ($intents === []) {
            $this->markDispatched($outbox);

            return 0;
        }

        $queuedDeliveries = 0;

        foreach ($intents as $intent) {
            $policyDecision = $this->policyResolver->decide($intent, $envelope->eventName, $envelope->campusId);
            $allowChannels = $policyDecision['allow_channels'];
            if ($allowChannels === []) {
                continue;
            }

            $resolved = $this->recipientResolver->resolve($intent->recipientTargets, $envelope->campusId);

            foreach ($resolved['unresolved'] as $unresolved) {
                $this->auditLogger->unresolvedRecipient([
                    'event_id' => $envelope->eventId,
                    'event_name' => $envelope->eventName,
                    'campus_id' => $envelope->campusId,
                    'target_type' => $unresolved['type'],
                    'target_id' => $unresolved['id'],
                    'reason' => $unresolved['reason'],
                ]);

                $this->metrics->increment('notification_recipient_unresolved_total', [
                    'event_name' => $envelope->eventName,
                    'campus_id' => $envelope->campusId,
                    'target_type' => $unresolved['type'],
                ]);
            }

            if ($resolved['resolved_user_ids'] === []) {
                continue;
            }

            $renderedEmail = $this->buildRenderedEmail($intent, $envelope);

            $deliveries = $this->persistIntentAction->run(
                $envelope,
                $intent,
                $resolved['resolved_user_ids'],
                $allowChannels,
                $renderedEmail,
            );

            foreach ($deliveries as $delivery) {
                SendNotificationDeliveryJob::dispatch($delivery->id);
                $queuedDeliveries++;
            }
        }

        $this->markDispatched($outbox);

        return $queuedDeliveries;
    }

    /**
     * Build rendered email content if a provider is registered for this type_key.
     * Returns empty array if no provider registered (old path).
     *
     * @return array{rendered_subject?: string, rendered_html?: string, rendered_text?: string|null}
     */
    private function buildRenderedEmail(NotificationIntent $intent, DomainEventEnvelope $envelope): array
    {
        if (! $this->emailContentRegistry->has($intent->typeKey)) {
            return [];
        }

        try {
            $data = $this->buildEmailData($intent, $envelope);
            $provider = $this->emailContentRegistry->resolve($intent->typeKey);

            return [
                'rendered_subject' => $provider->subject($data),
                'rendered_html' => $provider->htmlBody($data),
                'rendered_text' => $provider->textBody($data),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to render email content, falling back to old path', [
                'type_key' => $intent->typeKey,
                'event_name' => $envelope->eventName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build the data array for email content rendering.
     * Performs DB queries here so EmailContentProvider stays query-free.
     *
     * @return array<string, mixed>
     */
    private function buildEmailData(NotificationIntent $intent, DomainEventEnvelope $envelope): array
    {
        $data = $intent->data;

        $requestId = $data['dng_payment_request_id'] ?? null;

        if ($requestId !== null) {
            $request = DngPaymentRequest::with(['semester', 'student.program'])->find((int) $requestId);

            if ($request !== null) {
                $student = $request->student;
                $data['student_name'] = $student?->full_name ?? $data['student_name'] ?? '';
                $data['student_code'] = $request->student_code;
                $data['semester_code'] = $request->semester?->code ?? '';
                $data['program_name'] = $student?->program?->name ?? '';
                $data['invoice_code'] = $request->item_id;
                $data['amount_formatted'] = number_format((float) $request->amount, 0, ',', '.') . ' VNĐ';
                $data['due_date'] = $request->due_date?->format('d/m/Y') ?? null;
            }
        }

        return $data;
    }

    private function markDispatched(NotificationEventOutbox $outbox): void
    {
        $outbox->forceFill([
            'status' => NotificationOutboxStatus::Dispatched,
            'dispatched_at' => now(),
            'last_error' => null,
            'next_retry_at' => null,
        ])->save();
    }
}
