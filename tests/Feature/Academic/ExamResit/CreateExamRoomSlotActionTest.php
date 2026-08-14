<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CreateExamRoomSlotAction;
use App\Modules\Facilities\Models\RoomBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->room = Room::factory()->create([
        'campus_id' => $this->campus->id,
        'capacity' => 40,
    ]);
});

function runCreateExamRoomSlot(array $overrides = []): ExamRoomSlot
{
    return app(CreateExamRoomSlotAction::class)->run(array_merge([
        'campus_id' => test()->campus->id,
        'room_id' => test()->room->id,
        'exam_date' => '2026-07-01',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ], $overrides));
}

/**
 * Build a non-cancelled class session occupying the room on the same date/time.
 */
function classSessionInRoom(Room $room, string $date, string $start, string $end, string $status = 'scheduled'): ClassSession
{
    return ClassSession::factory()->create([
        'course_offering_id' => CourseOffering::factory()->create(['campus_id' => $room->campus_id]),
        'room_id' => $room->id,
        'session_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'status' => $status,
    ]);
}

/**
 * Build an active (or given-status) room booking occupying the room.
 */
function roomBookingInRoom(Room $room, string $date, string $start, string $end, string $status = RoomBooking::STATUS_APPROVED): RoomBooking
{
    return RoomBooking::create([
        'room_id' => $room->id,
        'booked_by_type' => 'user',
        'booked_by_id' => test()->user->id,
        'title' => 'Existing booking',
        'booking_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'booking_type' => 'meeting',
        'status' => $status,
    ]);
}

it('creates a scheduled exam room slot defaulting capacity to the room capacity', function () {
    $slot = runCreateExamRoomSlot();

    expect($slot->status)->toBe(ExamRoomSlot::STATUS_SCHEDULED)
        ->and($slot->capacity)->toBe(40)
        ->and($slot->campus_id)->toBe($this->campus->id)
        ->and($slot->room_id)->toBe($this->room->id)
        ->and($slot->created_by_user_id)->toBe($this->user->id)
        ->and($slot->start_time->format('H:i'))->toBe('09:00')
        ->and($slot->end_time->format('H:i'))->toBe('11:00');
});

it('honors an explicit capacity within the room capacity', function () {
    $slot = runCreateExamRoomSlot(['capacity' => 25]);

    expect($slot->capacity)->toBe(25);
});

it('uses a supplied Facilities reservation without creating a duplicate booking', function () {
    $booking = roomBookingInRoom($this->room, '2026-07-01', '09:00:00', '11:00:00');

    $slot = runCreateExamRoomSlot(['room_booking_id' => $booking->id]);

    expect($slot->room_booking_id)->toBe($booking->id)
        ->and(RoomBooking::query()->count())->toBe(1);
});

it('rejects a supplied reservation for a different campus', function () {
    $booking = roomBookingInRoom($this->room, '2026-07-01', '09:00:00', '11:00:00');

    runCreateExamRoomSlot([
        'campus_id' => Campus::factory()->create()->id,
        'room_booking_id' => $booking->id,
    ]);
})->throws(ValidationException::class);

it('rejects a supplied reservation whose requested capacity exceeds the room', function () {
    $booking = roomBookingInRoom($this->room, '2026-07-01', '09:00:00', '11:00:00');

    runCreateExamRoomSlot([
        'room_booking_id' => $booking->id,
        'capacity' => 100,
    ]);
})->throws(ValidationException::class);

it('rejects an inactive supplied reservation', function () {
    $booking = roomBookingInRoom(
        $this->room,
        '2026-07-01',
        '09:00:00',
        '11:00:00',
        RoomBooking::STATUS_CANCELLED,
    );

    runCreateExamRoomSlot(['room_booking_id' => $booking->id]);
})->throws(ValidationException::class);

it('rejects a capacity larger than the room capacity', function () {
    runCreateExamRoomSlot(['capacity' => 100]);
})->throws(ValidationException::class);

it('rejects an end time that is not after the start time', function () {
    runCreateExamRoomSlot(['start_time' => '11:00', 'end_time' => '11:00']);
})->throws(ValidationException::class);

it('rejects a room that does not belong to the campus', function () {
    $otherCampus = Campus::factory()->create();

    runCreateExamRoomSlot(['campus_id' => $otherCampus->id]);
})->throws(ValidationException::class);

it('rejects overlap with an active class session in the same room', function () {
    classSessionInRoom($this->room, '2026-07-01', '10:00:00', '12:00:00');

    runCreateExamRoomSlot();
})->throws(ValidationException::class);

it('rejects overlap with an active room booking in the same room', function () {
    roomBookingInRoom($this->room, '2026-07-01', '10:00:00', '12:00:00');

    runCreateExamRoomSlot();
})->throws(ValidationException::class);

it('rejects overlap with another scheduled exam room slot in the same room', function () {
    runCreateExamRoomSlot(['start_time' => '09:00', 'end_time' => '11:00']);

    runCreateExamRoomSlot(['start_time' => '10:00', 'end_time' => '12:00']);
})->throws(ValidationException::class);

it('allows an adjacent non-overlapping slot in the same room', function () {
    runCreateExamRoomSlot(['start_time' => '09:00', 'end_time' => '11:00']);

    $second = runCreateExamRoomSlot(['start_time' => '11:00', 'end_time' => '13:00']);

    expect($second->id)->not->toBeNull();
});

it('ignores cancelled class sessions and cancelled exam room slots when checking conflicts', function () {
    classSessionInRoom($this->room, '2026-07-01', '09:00:00', '11:00:00', status: 'cancelled');
    roomBookingInRoom($this->room, '2026-07-01', '09:00:00', '11:00:00', status: RoomBooking::STATUS_CANCELLED);
    ExamRoomSlot::factory()->cancelled()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'exam_date' => '2026-07-01',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    $slot = runCreateExamRoomSlot();

    expect($slot->status)->toBe(ExamRoomSlot::STATUS_SCHEDULED);
});
