<?php

declare(strict_types=1);

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Support\EventIntentMapper;
use Carbon\CarbonImmutable;

it('maps finance invoice paid event to a notification intent', function () {
    $event = new DomainEventEnvelope(
        eventId: 'evt-1',
        eventName: 'finance.invoice_paid',
        eventVersion: 1,
        occurredAt: CarbonImmutable::parse('2026-03-03 10:00:00'),
        aggregateType: 'payment',
        aggregateId: '123',
        campusId: 2,
        actorUserId: 10,
        payload: [
            'student_id' => 99,
            'channels' => ['email', 'realtime'],
            'data' => ['title' => 'Payment received'],
        ],
    );

    $intents = app(EventIntentMapper::class)->map($event);

    expect($intents)->toHaveCount(1)
        ->and($intents[0]->typeKey)->toBe('invoice_paid')
        ->and($intents[0]->channels)->toBe(['email', 'realtime'])
        ->and($intents[0]->recipientTargets)->toBe([
            ['type' => 'student', 'id' => 99],
        ]);
});

it('returns no intent for unknown event names', function () {
    $event = new DomainEventEnvelope(
        eventId: 'evt-2',
        eventName: 'unknown.anything',
        eventVersion: 1,
        occurredAt: CarbonImmutable::parse('2026-03-03 10:00:00'),
        aggregateType: 'unknown',
        aggregateId: '1',
        campusId: 1,
        actorUserId: null,
        payload: [],
    );

    expect(app(EventIntentMapper::class)->map($event))->toBe([]);
});
