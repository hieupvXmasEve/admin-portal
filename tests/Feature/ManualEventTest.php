<?php

use App\Models\Campus;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Carbon\Carbon;

beforeEach(function () {
    $this->eventService = app(EventService::class);
    $this->campus = Campus::factory()->create();
    $this->admin = User::factory()->create();

    // Associate admin with campus
    $this->admin->campuses()->attach($this->campus->id);
});

test('can create manual event with past dates', function () {
    $pastDate = Carbon::now()->subDays(30);
    $eventData = [
        'campus_id' => $this->campus->id,
        'title' => 'Historical Event',
        'description' => 'This event happened in the past',
        'start_time' => $pastDate->format('Y-m-d H:i:s'),
        'end_time' => $pastDate->addHours(2)->format('Y-m-d H:i:s'),
        'location' => 'Main Hall',
        'gold_reward_amount' => 50,
        'is_manual' => true,
        'is_historical' => true,
    ];

    $event = $this->eventService->createManualEvent($eventData, $this->admin);

    expect($event)->toBeInstanceOf(Event::class);
    expect($event->is_manual)->toBeTrue();
    expect($event->is_historical)->toBeTrue();
    expect($event->created_by_admin_id)->toBe($this->admin->id);
    expect($event->status)->toBe('completed');
    expect($event->completed_at)->not->toBeNull();
});

test('manual event has proper audit fields', function () {
    $eventData = [
        'campus_id' => $this->campus->id,
        'title' => 'Manual Event',
        'description' => 'Manually created event',
        'start_time' => Carbon::now()->subHour()->format('Y-m-d H:i:s'),
        'end_time' => Carbon::now()->format('Y-m-d H:i:s'),
        'location' => 'Conference Room',
        'gold_reward_amount' => 25,
        'is_manual' => true,
    ];

    $event = $this->eventService->createManualEvent($eventData, $this->admin);

    expect($event->is_manual)->toBeTrue();
    expect($event->created_by_user_id)->toBe($this->admin->id);
    expect($event->created_by_admin_id)->toBe($this->admin->id);
    expect($event->created_at)->not->toBeNull();
});

test('event factory creates manual events', function () {
    $manualEvent = Event::factory()->manual()->create([
        'campus_id' => $this->campus->id,
    ]);

    expect($manualEvent->is_manual)->toBeTrue();
    expect($manualEvent->created_by_admin_id)->not->toBeNull();
    expect($manualEvent->status)->toBe('completed');
});

test('event factory creates historical events', function () {
    $historicalEvent = Event::factory()->historical()->create([
        'campus_id' => $this->campus->id,
    ]);

    expect($historicalEvent->is_historical)->toBeTrue();
    expect($historicalEvent->is_manual)->toBeTrue();
    expect($historicalEvent->start_time->isPast())->toBeTrue();
    expect($historicalEvent->end_time->isPast())->toBeTrue();
    expect($historicalEvent->status)->toBe('completed');
});
