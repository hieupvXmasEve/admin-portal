<?php

declare(strict_types=1);

use App\Models\Building;
use App\Models\Campus;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Facilities\Actions\CreateRoomBookingSeriesAction;
use App\Modules\Facilities\Exceptions\RoomBookingSeriesConflictException;
use App\Modules\Facilities\Queries\PreviewRoomBookingSeriesAvailabilityQuery;
use App\Modules\Facilities\Support\ExamSlotBookingConflictChecker;
use App\Modules\Facilities\Support\RoomBookingService;
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

    $this->examDate = now()->addDays(4)->toDateString();
});

/**
 * @param  array<string,mixed>  $attributes
 */
function scheduledExamSlot(Campus $campus, Room $room, string $date, array $attributes = []): ExamRoomSlot
{
    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $campus->id,
        'room_id' => $room->id,
        'exam_date' => $date,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => ExamRoomSlot::STATUS_SCHEDULED,
        ...$attributes,
    ]);

    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'campus_id' => $campus->id,
    ]);

    return $slot;
}

it('detects a scheduled exam overlap via the conflict checker and builds a unit-coded title', function () {
    $unit = Unit::factory()->create(['code' => 'CS101']);
    $slot = scheduledExamSlot($this->campus, $this->room, $this->examDate);
    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
    ]);

    $conflicts = app(ExamSlotBookingConflictChecker::class)
        ->conflictsFor($this->room->id, $this->examDate, '10:00', '12:00');

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]['type'])->toBe('exam_room_slot')
        ->and($conflicts[0]['exam_room_slot_id'])->toBe($slot->id)
        ->and($conflicts[0]['title'])->toContain('CS101')
        ->and($conflicts[0]['is_editable'])->toBeFalse();
});

it('ignores completed, cancelled, and boundary-touching exam slots', function () {
    scheduledExamSlot($this->campus, $this->room, $this->examDate, ['status' => ExamRoomSlot::STATUS_COMPLETED, 'start_time' => '09:00:00', 'end_time' => '11:00:00']);
    scheduledExamSlot($this->campus, $this->room, $this->examDate, ['status' => ExamRoomSlot::STATUS_CANCELLED, 'start_time' => '13:00:00', 'end_time' => '15:00:00']);
    scheduledExamSlot($this->campus, $this->room, $this->examDate, ['start_time' => '11:00:00', 'end_time' => '13:00:00']);

    // Booking 09:00-11:00 only touches the 11:00-13:00 scheduled slot at the boundary.
    $conflicts = app(ExamSlotBookingConflictChecker::class)
        ->conflictsFor($this->room->id, $this->examDate, '09:00', '11:00');

    expect($conflicts)->toBeEmpty();
});

it('blocks the entire booking series when an occurrence overlaps a scheduled exam slot', function () {
    scheduledExamSlot($this->campus, $this->room, $this->examDate);

    try {
        app(CreateRoomBookingSeriesAction::class)->run([
            'room_id' => $this->room->id,
            'title' => 'Exam-conflicting workshop',
            'booking_type' => RoomBooking::TYPE_WORKSHOP,
            'priority' => RoomBooking::PRIORITY_NORMAL,
            'occurrences' => [
                ['booking_date' => $this->examDate, 'start_time' => '09:30', 'end_time' => '10:30'],
            ],
        ], RoomBooking::BOOKED_BY_USER, $this->user->id, $this->user, $this->campus->id);

        $this->fail('Expected a room booking series conflict for the exam overlap.');
    } catch (RoomBookingSeriesConflictException $exception) {
        expect(RoomBooking::query()->count())->toBe(0)
            ->and(collect($exception->conflicts())->pluck('type'))->toContain('exam_room_slot');
    }
});

it('reports the exam overlap in the series availability preview', function () {
    scheduledExamSlot($this->campus, $this->room, $this->examDate);

    $preview = app(PreviewRoomBookingSeriesAvailabilityQuery::class)->handle(
        $this->room->id,
        [['booking_date' => $this->examDate, 'start_time' => '09:30', 'end_time' => '10:30']],
        $this->campus->id,
    );

    expect($preview['has_conflicts'])->toBeTrue()
        ->and(collect($preview['conflicts'])->pluck('type'))->toContain('exam_room_slot')
        ->and($preview['occurrences'][0]['available'])->toBeFalse();
});

it('reports the exam overlap in the single-day conflict check', function () {
    scheduledExamSlot($this->campus, $this->room, $this->examDate);

    $conflicts = app(RoomBookingService::class)->checkAllConflicts(
        $this->room->id,
        $this->examDate,
        '09:30',
        '10:30',
    );

    expect(collect($conflicts)->pluck('type'))->toContain('exam_room_slot');
});

it('blocks updating a booking into a scheduled exam window', function () {
    scheduledExamSlot($this->campus, $this->room, $this->examDate);

    $booking = RoomBooking::create([
        'room_id' => $this->room->id,
        'booked_by_type' => RoomBooking::BOOKED_BY_USER,
        'booked_by_id' => $this->user->id,
        'title' => 'Movable booking',
        'booking_date' => $this->examDate,
        'start_time' => '14:00',
        'end_time' => '15:00',
        'booking_type' => RoomBooking::TYPE_MEETING,
        'status' => RoomBooking::STATUS_APPROVED,
        'priority' => RoomBooking::PRIORITY_NORMAL,
    ]);

    expect(fn () => app(RoomBookingService::class)->updateBooking(
        $booking,
        [
            'room_id' => $this->room->id,
            'booking_date' => $this->examDate,
            'start_time' => '09:30',
            'end_time' => '10:30',
        ],
        RoomBooking::BOOKED_BY_USER,
        $this->user->id,
    ))->toThrow(InvalidArgumentException::class);
});

it('blocks approving a pending booking that overlaps a scheduled exam slot', function () {
    $booking = RoomBooking::create([
        'room_id' => $this->room->id,
        'booked_by_type' => RoomBooking::BOOKED_BY_USER,
        'booked_by_id' => $this->user->id,
        'title' => 'Pending booking',
        'booking_date' => $this->examDate,
        'start_time' => '09:30',
        'end_time' => '10:30',
        'booking_type' => RoomBooking::TYPE_MEETING,
        'status' => RoomBooking::STATUS_PENDING,
        'priority' => RoomBooking::PRIORITY_NORMAL,
    ]);

    // Exam scheduled AFTER the booking was submitted.
    scheduledExamSlot($this->campus, $this->room, $this->examDate);

    expect(fn () => app(RoomBookingService::class)->approveBooking($booking, 'user', $this->user->id))
        ->toThrow(InvalidArgumentException::class);

    expect($booking->fresh()->status)->toBe(RoomBooking::STATUS_PENDING);
});

it('still approves a booking whose only exam overlap is its own mirror slot', function () {
    $booking = RoomBooking::create([
        'room_id' => $this->room->id,
        'booked_by_type' => RoomBooking::BOOKED_BY_USER,
        'booked_by_id' => $this->user->id,
        'title' => 'Self-mirrored exam booking',
        'booking_date' => $this->examDate,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'booking_type' => RoomBooking::TYPE_EXAM,
        'status' => RoomBooking::STATUS_PENDING,
        'priority' => RoomBooking::PRIORITY_NORMAL,
    ]);

    scheduledExamSlot($this->campus, $this->room, $this->examDate, ['room_booking_id' => $booking->id]);

    $approved = app(RoomBookingService::class)->approveBooking($booking, 'user', $this->user->id);

    expect($approved->status)->toBe(RoomBooking::STATUS_APPROVED);
});
