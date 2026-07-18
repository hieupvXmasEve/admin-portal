<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Illuminate\Support\Facades\DB;

final class NotificationDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private readonly PublishDomainEventAction $publishDomainEvent,
    ) {}

    public function publish(DomainEvent $event): void
    {
        if (! $this->isEnabled($event)) {
            return;
        }

        $this->publishDomainEvent->run(new DomainEventEnvelope(
            eventId: $event->eventId(),
            eventName: $event->name,
            eventVersion: $event->version,
            occurredAt: $event->occurredAt,
            aggregateType: $event->aggregateType,
            aggregateId: $event->aggregateId,
            campusId: $event->campusId,
            actorUserId: $event->actorUserId,
            payload: $event->payload,
        ));
    }

    public function publishAfterCommit(DomainEvent $event): void
    {
        if (! $this->isEnabled($event)) {
            return;
        }

        DB::afterCommit(fn () => $this->publish($event));
    }

    private function isEnabled(DomainEvent $event): bool
    {
        if ($event->name === 'academic.warning_sent') {
            return true;
        }

        if (! (bool) config('notification.v2_enabled', false)) {
            return false;
        }

        return in_array((string) config('notification.write_mode', 'off'), ['dual', 'v2', 'v2_only'], true);
    }
}
