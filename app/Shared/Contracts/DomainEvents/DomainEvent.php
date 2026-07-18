<?php

declare(strict_types=1);

namespace App\Shared\Contracts\DomainEvents;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class DomainEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $name,
        public readonly string $deduplicationKey,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $aggregateType,
        public readonly string $aggregateId,
        public readonly ?int $campusId,
        public readonly ?int $actorUserId,
        public readonly array $payload,
        public readonly int $version = 1,
    ) {
        if ($this->name === '' || $this->deduplicationKey === '' || $this->aggregateType === '' || $this->aggregateId === '') {
            throw new InvalidArgumentException('Domain events require a name, deduplication key, and aggregate identity.');
        }
    }

    public function eventId(): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'swinx:domain-event:'.$this->deduplicationKey)->toString();
    }
}
