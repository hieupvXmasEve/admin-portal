<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Shared\Contracts\Facilities\DTO\SpaceReservationRequest;
use App\Shared\Contracts\Facilities\SpaceAvailabilityReader;
use App\Shared\Contracts\Facilities\SpaceReservationContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an approved Facilities reservation for an exam scheduling intent', function (): void {
    $campus = Campus::factory()->create();
    $room = Room::factory()->create([
        'campus_id' => $campus->id,
        'capacity' => 40,
        'available_from' => '07:00:00',
        'available_until' => '20:00:00',
    ]);
    $user = User::factory()->create();

    $reservation = app(SpaceReservationContract::class)->reserve(new SpaceReservationRequest(
        campusId: $campus->id,
        roomId: $room->id,
        date: now()->addDays(2)->toDateString(),
        startTime: '09:00',
        endTime: '11:00',
        requestedCapacity: 25,
        title: 'Thi lại',
        requestedByUserId: $user->id,
    ));

    expect($reservation->roomId)->toBe($room->id)
        ->and($reservation->capacity)->toBe(25)
        ->and(RoomBooking::query()->findOrFail($reservation->reservationId))
        ->booking_type->toBe(RoomBooking::TYPE_EXAM)
        ->status->toBe(RoomBooking::STATUS_APPROVED);
});

it('reports an occupied Delivery slot as unavailable through the Facilities reader', function (): void {
    $campus = Campus::factory()->create();
    $room = Room::factory()->create([
        'campus_id' => $campus->id,
        'available_from' => '07:00:00',
        'available_until' => '20:00:00',
    ]);
    $user = User::factory()->create();
    $date = now()->addDays(3)->toDateString();

    app(SpaceReservationContract::class)->reserve(new SpaceReservationRequest(
        campusId: $campus->id,
        roomId: $room->id,
        date: $date,
        startTime: '09:00',
        endTime: '11:00',
        requestedCapacity: null,
        title: 'Thi lại',
        requestedByUserId: $user->id,
    ));

    $availability = app(SpaceAvailabilityReader::class)->check(new SpaceReservationRequest(
        campusId: $campus->id,
        roomId: $room->id,
        date: $date,
        startTime: '10:00',
        endTime: '12:00',
        requestedCapacity: null,
        title: 'Lịch khác',
        requestedByUserId: $user->id,
    ));

    expect($availability->available)->toBeFalse()
        ->and($availability->conflicts)->not->toBeEmpty();
});
