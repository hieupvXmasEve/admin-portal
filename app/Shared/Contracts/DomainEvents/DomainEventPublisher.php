<?php

declare(strict_types=1);

namespace App\Shared\Contracts\DomainEvents;

interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;

    public function publishAfterCommit(DomainEvent $event): void;
}
