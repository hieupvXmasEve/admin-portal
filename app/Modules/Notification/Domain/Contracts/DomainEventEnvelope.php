<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Contracts;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class DomainEventEnvelope
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventName,
        public readonly int $eventVersion,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $aggregateType,
        public readonly string $aggregateId,
        public readonly ?int $campusId,
        public readonly ?int $actorUserId,
        public readonly array $payload,
    ) {
        if ($this->eventId === '' || $this->eventName === '' || $this->aggregateType === '' || $this->aggregateId === '') {
            throw new InvalidArgumentException('Invalid domain event envelope: required fields are missing.');
        }
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            eventId: (string) ($attributes['event_id'] ?? ''),
            eventName: (string) ($attributes['event_name'] ?? ''),
            eventVersion: (int) ($attributes['event_version'] ?? 1),
            occurredAt: CarbonImmutable::parse($attributes['occurred_at'] ?? now()),
            aggregateType: (string) ($attributes['aggregate_type'] ?? ''),
            aggregateId: (string) ($attributes['aggregate_id'] ?? ''),
            campusId: isset($attributes['campus_id']) ? (int) $attributes['campus_id'] : null,
            actorUserId: isset($attributes['actor_user_id']) ? (int) $attributes['actor_user_id'] : null,
            payload: (array) ($attributes['payload'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'event_version' => $this->eventVersion,
            'occurred_at' => $this->occurredAt->toDateTimeString(),
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'campus_id' => $this->campusId,
            'actor_user_id' => $this->actorUserId,
            'payload' => $this->payload,
        ];
    }
}
