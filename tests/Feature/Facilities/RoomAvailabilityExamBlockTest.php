<?php

declare(strict_types=1);

use App\Models\Building;
use App\Models\Campus;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Modules\Facilities\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Facilities\Models\RoomBooking;
use App\Modules\Facilities\Queries\GetRoomAvailabilityBoardQuery;
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

    $this->examDate = now()->addDays(2)->toDateString();
    $this->filters = [
        'campus_id' => $this->campus->id,
        'start_date' => $this->examDate,
        'end_date' => $this->examDate,
        'start_time' => '07:00',
        'end_time' => '20:00',
    ];
});

/**
 * Returns the single room/date day cell for the seeded room and exam date.
 *
 * @param  array<string,mixed>  $board
 * @return array<string,mixed>
 */
function dayCell(array $board, int $roomId, string $date): array
{
    $room = collect($board['rooms'])->firstWhere('id', $roomId);

    return collect($room['days'])->firstWhere('date', $date);
}

it('includes scheduled exam blocks as exam_room_slot events on the board', function () {
    $unit = Unit::factory()->create(['code' => 'CS101', 'name' => 'Intro to CS']);
    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'exam_date' => $this->examDate,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => ExamRoomSlot::STATUS_SCHEDULED,
    ]);
    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
    ]);

    $board = app(GetRoomAvailabilityBoardQuery::class)->handle($this->filters);
    $events = dayCell($board, $this->room->id, $this->examDate)['events'];

    $examEvent = collect($events)->firstWhere('type', 'exam_room_slot');

    expect($examEvent)->not->toBeNull()
        ->and($examEvent['exam_room_slot_id'])->toBe($slot->id)
        ->and($examEvent['start_time'])->toBe('09:00')
        ->and($examEvent['end_time'])->toBe('11:00')
        ->and($examEvent['is_editable'])->toBeFalse()
        ->and($examEvent['title'])->toContain('CS101');
});

it('subtracts the exam window from free windows but keeps the room bookable in other gaps', function () {
    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'exam_date' => $this->examDate,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => ExamRoomSlot::STATUS_SCHEDULED,
    ]);
    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'campus_id' => $this->campus->id,
    ]);

    $board = app(GetRoomAvailabilityBoardQuery::class)->handle($this->filters);
    $day = dayCell($board, $this->room->id, $this->examDate);

    $windows = collect($day['free_windows'])
        ->map(fn (array $w) => $w['start_time'].'-'.$w['end_time'])
        ->all();

    expect($day['is_bookable'])->toBeTrue()
        ->and($windows)->toContain('07:00-09:00')
        ->and($windows)->toContain('11:00-20:00')
        ->and($windows)->not->toContain('07:00-20:00');
});

it('ignores cancelled and completed exam slots', function () {
    ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'exam_date' => $this->examDate,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => ExamRoomSlot::STATUS_CANCELLED,
    ]);
    ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'exam_date' => $this->examDate,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => ExamRoomSlot::STATUS_COMPLETED,
    ]);

    $board = app(GetRoomAvailabilityBoardQuery::class)->handle($this->filters);
    $events = dayCell($board, $this->room->id, $this->examDate)['events'];

    expect(collect($events)->where('type', 'exam_room_slot'))->toHaveCount(0);
});

it('does not double-display an exam slot already mirrored as an active room booking', function () {
    $booking = RoomBooking::create([
        'room_id' => $this->room->id,
        'booked_by_type' => RoomBooking::BOOKED_BY_USER,
        'booked_by_id' => $this->user->id,
        'title' => 'Exam block (mirrored)',
        'booking_date' => $this->examDate,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'booking_type' => RoomBooking::TYPE_MEETING,
        'status' => RoomBooking::STATUS_APPROVED,
        'priority' => RoomBooking::PRIORITY_NORMAL,
    ]);

    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'room_booking_id' => $booking->id,
        'exam_date' => $this->examDate,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => ExamRoomSlot::STATUS_SCHEDULED,
    ]);
    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'campus_id' => $this->campus->id,
    ]);

    $board = app(GetRoomAvailabilityBoardQuery::class)->handle($this->filters);
    $events = collect(dayCell($board, $this->room->id, $this->examDate)['events']);

    expect($events->where('type', 'exam_room_slot'))->toHaveCount(0)
        ->and($events->where('type', 'room_booking'))->toHaveCount(1);
});
