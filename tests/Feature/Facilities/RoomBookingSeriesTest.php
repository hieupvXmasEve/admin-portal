<?php

declare(strict_types=1);

use App\Models\Building;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Modules\Facilities\Models\Room;
use App\Models\User;
use App\Modules\Facilities\Actions\CreateRoomBookingSeriesAction;
use App\Modules\Facilities\Exceptions\RoomBookingSeriesConflictException;
use App\Modules\Facilities\Models\RoomBooking;
use App\Modules\Facilities\Models\RoomBookingAction;
use App\Modules\Facilities\Queries\GetRoomBookingCloneDraftQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->building = Building::factory()->forCampus($this->campus)->create();
    $this->room = Room::factory()->forBuilding($this->building)->create([
        'available_from' => '07:00:00',
        'available_until' => '20:00:00',
    ]);
    $this->user = User::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('creates a multi-day booking set as grouped single-day occurrences', function () {
    $dates = [
        now()->addDays(3)->toDateString(),
        now()->addDays(4)->toDateString(),
        now()->addDays(5)->toDateString(),
    ];

    $result = app(CreateRoomBookingSeriesAction::class)->run([
        'room_id' => $this->room->id,
        'title' => 'Operations workshop',
        'description' => null,
        'booking_type' => RoomBooking::TYPE_WORKSHOP,
        'priority' => RoomBooking::PRIORITY_NORMAL,
        'occurrences' => collect($dates)->map(fn (string $date) => [
            'booking_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])->all(),
    ], RoomBooking::BOOKED_BY_USER, $this->user->id, $this->user, $this->campus->id);

    expect($result['bookings'])->toHaveCount(3);

    $parent = $result['parent'];

    expect($parent->parent_booking_id)->toBeNull()
        ->and($parent->is_recurring)->toBeTrue()
        ->and($parent->recurrence_end_date->format('Y-m-d'))->toBe($dates[2]);

    expect(RoomBooking::query()->where('parent_booking_id', $parent->id)->count())->toBe(2)
        ->and(RoomBookingAction::query()->where('action_type', RoomBookingAction::ACTION_CREATED)->count())->toBe(3);
});

it('blocks the entire booking set when one occurrence conflicts with an active booking', function () {
    $conflictDate = now()->addDays(3)->toDateString();

    RoomBooking::create([
        'room_id' => $this->room->id,
        'booked_by_type' => RoomBooking::BOOKED_BY_USER,
        'booked_by_id' => $this->user->id,
        'title' => 'Existing booking',
        'booking_date' => $conflictDate,
        'start_time' => '09:30',
        'end_time' => '10:30',
        'booking_type' => RoomBooking::TYPE_MEETING,
        'status' => RoomBooking::STATUS_APPROVED,
        'priority' => RoomBooking::PRIORITY_NORMAL,
    ]);

    try {
        app(CreateRoomBookingSeriesAction::class)->run([
            'room_id' => $this->room->id,
            'title' => 'Blocked workshop',
            'booking_type' => RoomBooking::TYPE_WORKSHOP,
            'priority' => RoomBooking::PRIORITY_NORMAL,
            'occurrences' => [
                ['booking_date' => $conflictDate, 'start_time' => '09:00', 'end_time' => '11:00'],
                ['booking_date' => now()->addDays(4)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00'],
            ],
        ], RoomBooking::BOOKED_BY_USER, $this->user->id, $this->user, $this->campus->id);

        $this->fail('Expected a room booking series conflict.');
    } catch (RoomBookingSeriesConflictException $exception) {
        expect($exception->conflicts())->not->toBeEmpty()
            ->and(RoomBooking::query()->count())->toBe(1);
    }
});

it('detects class session conflicts during series creation', function () {
    $conflictDate = now()->addDays(6)->toDateString();

    ClassSession::factory()->create([
        'room_id' => $this->room->id,
        'session_date' => $conflictDate,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'status' => 'scheduled',
    ]);

    app(CreateRoomBookingSeriesAction::class)->run([
        'room_id' => $this->room->id,
        'title' => 'Class conflict request',
        'booking_type' => RoomBooking::TYPE_MEETING,
        'priority' => RoomBooking::PRIORITY_NORMAL,
        'occurrences' => [
            ['booking_date' => $conflictDate, 'start_time' => '15:00', 'end_time' => '17:00'],
        ],
    ], RoomBooking::BOOKED_BY_USER, $this->user->id, $this->user, $this->campus->id);
})->throws(RoomBookingSeriesConflictException::class);

it('builds a clone draft without copying workflow state', function () {
    $booking = RoomBooking::create([
        'room_id' => $this->room->id,
        'booked_by_type' => RoomBooking::BOOKED_BY_USER,
        'booked_by_id' => $this->user->id,
        'approved_by_type' => 'user',
        'approved_by_id' => $this->user->id,
        'title' => 'Original booking',
        'booking_date' => now()->addDays(2)->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'booking_type' => RoomBooking::TYPE_EVENT,
        'status' => RoomBooking::STATUS_APPROVED,
        'priority' => RoomBooking::PRIORITY_HIGH,
        'approved_at' => now(),
    ]);

    $draft = app(GetRoomBookingCloneDraftQuery::class)->handle($booking);

    expect($draft['source_booking_id'])->toBe($booking->id)
        ->and($draft['room_id'])->toBe($booking->room_id)
        ->and($draft['title'])->toBe('Original booking')
        ->and($draft)->not->toHaveKey('status')
        ->and($draft)->not->toHaveKey('approved_by_id')
        ->and($draft)->not->toHaveKey('approved_at');
});
