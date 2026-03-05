<?php

declare(strict_types=1);

use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Actions\SendManualNotificationV2Action;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;

it('creates domain event envelope with correct structure', function () {
    $publishAction = Mockery::mock(PublishDomainEventAction::class);
    $action = new SendManualNotificationV2Action($publishAction);

    $capturedEnvelope = null;
    $publishAction
        ->shouldReceive('run')
        ->once()
        ->withArgs(function (DomainEventEnvelope $envelope) use (&$capturedEnvelope) {
            $capturedEnvelope = $envelope;

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
    expect($capturedEnvelope)->not->toBeNull();
    expect($capturedEnvelope->eventName)->toBe('manual.notification_sent');
    expect($capturedEnvelope->campusId)->toBe(1);
    expect($capturedEnvelope->payload['type_key'])->toBe('manual_notification');
    expect($capturedEnvelope->payload['recipient_targets'])->toBe([
        ['type' => 'student', 'id' => 100],
        ['type' => 'student', 'id' => 200],
    ]);
    expect($capturedEnvelope->payload['channels'])->toBe(['realtime']);
    expect($capturedEnvelope->payload['data']['title'])->toBe('Test Notification');
    expect($capturedEnvelope->payload['data']['body'])->toBe('This is a test message');
    expect($capturedEnvelope->payload['data']['is_important'])->toBeTrue();
});

it('filters invalid notifiable ids', function () {
    $publishAction = Mockery::mock(PublishDomainEventAction::class);
    $action = new SendManualNotificationV2Action($publishAction);

    $capturedEnvelope = null;
    $publishAction->shouldReceive('run')->once()
        ->withArgs(function (DomainEventEnvelope $envelope) use (&$capturedEnvelope) {
            $capturedEnvelope = $envelope;

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

    expect($capturedEnvelope->payload['recipient_targets'])->toBe([
        ['type' => 'user', 'id' => 1],
        ['type' => 'user', 'id' => 2],
    ]);
});

it('sets default values for optional fields', function () {
    $publishAction = Mockery::mock(PublishDomainEventAction::class);
    $action = new SendManualNotificationV2Action($publishAction);

    $capturedEnvelope = null;
    $publishAction->shouldReceive('run')->once()
        ->withArgs(function (DomainEventEnvelope $envelope) use (&$capturedEnvelope) {
            $capturedEnvelope = $envelope;

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

    expect($capturedEnvelope->payload['data']['is_important'])->toBeFalse();
    expect($capturedEnvelope->payload['data']['action_url'])->toBeNull();
    expect($capturedEnvelope->payload['data']['action_text'])->toBeNull();
});

it('maps manual event in EventIntentMapper', function () {
    $event = new DomainEventEnvelope(
        eventId: 'evt-manual-1',
        eventName: 'manual.notification_sent',
        eventVersion: 1,
        occurredAt: \Carbon\CarbonImmutable::now(),
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

    $intents = app(\App\Modules\Notification\Support\EventIntentMapper::class)->map($event);

    expect($intents)->toHaveCount(1)
        ->and($intents[0]->typeKey)->toBe('manual_notification')
        ->and($intents[0]->channels)->toBe(['realtime'])
        ->and($intents[0]->recipientTargets)->toBe([
            ['type' => 'student', 'id' => 100],
        ]);
});
