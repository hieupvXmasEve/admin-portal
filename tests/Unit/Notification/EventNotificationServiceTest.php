<?php

declare(strict_types=1);

use App\Modules\Engagement\Actions\EventNotificationPublisher;
use App\Modules\Engagement\Models\Event;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Carbon\CarbonImmutable;

it('publishes event registration confirmations through the shared publisher', function (): void {
    $publisher = Mockery::mock(DomainEventPublisher::class);
    $service = eventNotificationPublisher($publisher);
    $event = eventFixture();

    $capturedEvent = null;
    $publisher->shouldReceive('publishAfterCommit')
        ->once()
        ->withArgs(function (DomainEvent $domainEvent) use (&$capturedEvent): bool {
            $capturedEvent = $domainEvent;

            return true;
        });

    $service->sendEventRegistrationConfirmation(42, $event);

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->name)->toBe('event.registration_confirmed')
        ->and($capturedEvent->deduplicationKey)->toBe('event:7:student:42:registration_confirmed')
        ->and($capturedEvent->campusId)->toBe(3)
        ->and($capturedEvent->payload['channels'])->toBe(['realtime'])
        ->and($capturedEvent->payload['recipient_targets'])->toBe([
            ['type' => 'student', 'id' => 42],
        ])
        ->and($capturedEvent->payload['type_key'])->toBe('event_registration');
});

it('keeps gold notification types distinct for idempotency and user preferences', function (): void {
    $publisher = Mockery::mock(DomainEventPublisher::class);
    $service = eventNotificationPublisher($publisher);
    $event = eventFixture();

    $capturedEvent = null;
    $publisher->shouldReceive('publishAfterCommit')
        ->once()
        ->withArgs(function (DomainEvent $domainEvent) use (&$capturedEvent): bool {
            $capturedEvent = $domainEvent;

            return true;
        });

    $service->sendGoldRewardNotification(42, 100.0, $event);

    expect($capturedEvent->name)->toBe('event.gold_earned')
        ->and($capturedEvent->deduplicationKey)->toBe('event:7:student:42:gold:earned:100')
        ->and($capturedEvent->payload['type_key'])->toBe('event_gold_reward')
        ->and($capturedEvent->payload['data']['is_important'])->toBeTrue();
});

function eventFixture(): Event
{
    $event = new Event([
        'campus_id' => 3,
        'title' => 'Orientation day',
        'start_time' => CarbonImmutable::parse('2026-08-01 09:00:00'),
        'end_time' => CarbonImmutable::parse('2026-08-01 12:00:00'),
        'location' => 'Hall A',
        'gold_reward_amount' => 100,
        'qr_code' => 'event-qr',
        'created_by_user_id' => 9,
    ]);
    $event->id = 7;

    return $event;
}

function eventNotificationPublisher(DomainEventPublisher $publisher): EventNotificationPublisher
{
    return new EventNotificationPublisher(
        $publisher,
        Mockery::mock(StudentReferenceReader::class),
        Mockery::mock(StudentLifecycleStatusReader::class),
    );
}
