<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Policies\PolicyResolver;
use App\Modules\Notification\Support\EventIntentMapper;
use App\Modules\Notification\Support\NotificationAuditLogger;
use App\Modules\Notification\Support\NotificationMetrics;
use App\Modules\Notification\Support\RecipientResolver;

class HandleOutboxEventAction
{
    public function __construct(
        private readonly EventIntentMapper $intentMapper,
        private readonly PolicyResolver $policyResolver,
        private readonly RecipientResolver $recipientResolver,
        private readonly PersistIntentAction $persistIntentAction,
        private readonly NotificationAuditLogger $auditLogger,
        private readonly NotificationMetrics $metrics,
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

            $deliveries = $this->persistIntentAction->run(
                $envelope,
                $intent,
                $resolved['resolved_user_ids'],
                $allowChannels,
            );

            foreach ($deliveries as $delivery) {
                SendNotificationDeliveryJob::dispatch($delivery->id);
                $queuedDeliveries++;
            }
        }

        $this->markDispatched($outbox);

        return $queuedDeliveries;
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
