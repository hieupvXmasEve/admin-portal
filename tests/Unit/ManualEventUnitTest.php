<?php

use App\Models\Event;
use App\Services\EventService;
use Carbon\Carbon;

test('event model has manual and historical methods', function () {
    $event = new Event([
        'is_manual' => true,
        'is_historical' => false,
    ]);

    expect($event->isManual())->toBeTrue();
    expect($event->isHistorical())->toBeFalse();
});

test('event model has historical methods', function () {
    $event = new Event([
        'is_manual' => true,
        'is_historical' => true,
    ]);

    expect($event->isManual())->toBeTrue();
    expect($event->isHistorical())->toBeTrue();
});

test('event validation allows past dates for manual events', function () {
    $eventService = app(EventService::class);

    // Use reflection to test the protected method
    $reflection = new ReflectionClass($eventService);
    $method = $reflection->getMethod('validateEventData');
    $method->setAccessible(true);

    $pastDate = Carbon::now()->subDays(1);
    $data = [
        'campus_id' => 1,
        'start_time' => $pastDate->format('Y-m-d H:i:s'),
        'end_time' => $pastDate->addHours(2)->format('Y-m-d H:i:s'),
        'is_manual' => true,
        'is_historical' => true,
    ];

    // This should not throw an exception for manual events
    expect(fn() => $method->invoke($eventService, $data))->not->toThrow(Exception::class);
});

test('event validation rejects past dates for regular events', function () {
    $eventService = app(EventService::class);

    // Use reflection to test the protected method
    $reflection = new ReflectionClass($eventService);
    $method = $reflection->getMethod('validateEventData');
    $method->setAccessible(true);

    $pastDate = Carbon::now()->subDays(1);
    $data = [
        'campus_id' => 1,
        'start_time' => $pastDate->format('Y-m-d H:i:s'),
        'end_time' => $pastDate->addHours(2)->format('Y-m-d H:i:s'),
        'is_manual' => false,
        'is_historical' => false,
    ];

    // This should throw an exception for regular events
    expect(fn() => $method->invoke($eventService, $data))->toThrow(Throwable::class);
});
