<?php

declare(strict_types=1);

use App\Modules\Notification\Actions\SendManualNotificationV2Action;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Support\EventIntentMapper;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;

it('publishes a shared domain event after commit with the correct structure', function () {
    $publisher = Mockery::mock(DomainEventPublisher::class);
    $action = new SendManualNotificationV2Action($publisher);

    $capturedEvent = null;
    $publisher
        ->shouldReceive('publishAfterCommit')
        ->once()
        ->withArgs(function (DomainEvent $event) use (&$capturedEvent) {
            $capturedEvent = $event;

            return true;
        });

    $data = [
        'campus_id' => 1,
        'notifiable_type' => 'student',
        'notifiable_ids' => [100, 200],
        'title' => 'Test Notification',
        'message' => 'This is a test message',
        'category' => 'system',
        'is_important' => true,
        'action_url' => '/dashboard',
        'action_text' => 'View',
        'actor_user_id' => 5,
    ];

    $eventId = $action->run($data);

    expect($eventId)->toBeString()->not->toBeEmpty();
    expect($capturedEvent)->not->toBeNull();
    expect($capturedEvent->name)->toBe('manual.notification_sent');
    expect($capturedEvent->campusId)->toBe(1);
    expect($capturedEvent->payload['type_key'])->toBe('manual_notification');
    expect($capturedEvent->payload['recipient_targets'])->toBe([
        ['type' => 'student', 'id' => 100],
        ['type' => 'student', 'id' => 200],
    ]);
    expect($capturedEvent->payload['channels'])->toBe(['realtime']);
    expect($capturedEvent->payload['data']['title'])->toBe('Test Notification');
    expect($capturedEvent->payload['data']['body'])->toBe('This is a test message');
    expect($capturedEvent->payload['data']['is_important'])->toBeTrue();
});

it('filters invalid notifiable ids', function () {
    $publisher = Mockery::mock(DomainEventPublisher::class);
    $action = new SendManualNotificationV2Action($publisher);

    $capturedEvent = null;
    $publisher->shouldReceive('publishAfterCommit')->once()
        ->withArgs(function (DomainEvent $event) use (&$capturedEvent) {
            $capturedEvent = $event;

            return true;
        });

    $data = [
        'campus_id' => 1,
        'notifiable_type' => 'user',
        'notifiable_ids' => [1, 0, -1, 2],
        'title' => 'Test',
        'message' => 'Test',
        'category' => 'system',
        'actor_user_id' => 1,
    ];

    $action->run($data);

    expect($capturedEvent->payload['recipient_targets'])->toBe([
        ['type' => 'user', 'id' => 1],
        ['type' => 'user', 'id' => 2],
    ]);
});

it('sets default values for optional fields', function () {
    $publisher = Mockery::mock(DomainEventPublisher::class);
    $action = new SendManualNotificationV2Action($publisher);

    $capturedEvent = null;
    $publisher->shouldReceive('publishAfterCommit')->once()
        ->withArgs(function (DomainEvent $event) use (&$capturedEvent) {
            $capturedEvent = $event;

            return true;
        });

    $data = [
        'campus_id' => 1,
        'notifiable_type' => 'student',
        'notifiable_ids' => [1],
        'title' => 'Test',
        'message' => 'Test',
        'category' => 'system',
        'actor_user_id' => 1,
    ];

    $action->run($data);

    expect($capturedEvent->payload['data']['is_important'])->toBeFalse();
    expect($capturedEvent->payload['data']['action_url'])->toBeNull();
    expect($capturedEvent->payload['data']['action_text'])->toBeNull();
});

it('maps manual event in EventIntentMapper', function () {
    $event = new DomainEventEnvelope(
        eventId: 'evt-manual-1',
        eventName: 'manual.notification_sent',
        eventVersion: 1,
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'manual_notification',
        aggregateId: 'evt-manual-1',
        campusId: 1,
        actorUserId: 5,
        payload: [
            'type_key' => 'manual_notification',
            'recipient_targets' => [['type' => 'student', 'id' => 100]],
            'channels' => ['realtime'],
            'data' => [
                'title' => 'Test',
                'body' => 'Test body',
                'category' => 'system',
                'is_important' => false,
            ],
        ],
    );

    $intents = app(EventIntentMapper::class)->map($event);

    expect($intents)->toHaveCount(1)
        ->and($intents[0]->typeKey)->toBe('manual_notification')
        ->and($intents[0]->channels)->toBe(['realtime'])
        ->and($intents[0]->recipientTargets)->toBe([
            ['type' => 'student', 'id' => 100],
        ]);
});
