<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\DispatchSingleOutboxEventJob;
use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Support\Facades\DB;

class PublishDomainEventAction
{
    public function run(DomainEventEnvelope $envelope): NotificationEventOutbox
    {
        $outbox = NotificationEventOutbox::query()->firstOrCreate(
            ['event_id' => $envelope->eventId],
            [
                'event_name' => $envelope->eventName,
                'event_version' => $envelope->eventVersion,
                'occurred_at' => $envelope->occurredAt,
                'aggregate_type' => $envelope->aggregateType,
                'aggregate_id' => $envelope->aggregateId,
                'campus_id' => $envelope->campusId,
                'actor_user_id' => $envelope->actorUserId,
                'payload' => $envelope->payload,
                'status' => NotificationOutboxStatus::Pending,
            ]
        );

        if ($outbox->wasRecentlyCreated && (bool) config('notification.outbox.push_enabled', true)) {
            DispatchSingleOutboxEventJob::dispatch($outbox->id)->afterCommit();
        }

        return $outbox;
    }

    public function runAfterCommit(DomainEventEnvelope $envelope): void
    {
        DB::afterCommit(fn () => $this->run($envelope));
    }
}
