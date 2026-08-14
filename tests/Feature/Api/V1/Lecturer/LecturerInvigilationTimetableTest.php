<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\Lecturer\TimetableResource;
use App\Models\Campus;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Modules\Facilities\Models\Room;
use App\Models\Semester;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Support\LecturerTimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: ExamRoomSlot, 1: Campus, 2: Semester}
 */
function invigilationSlot(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $room = Room::factory()->create(['campus_id' => $campus->id, 'capacity' => 30]);

    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $campus->id,
        'room_id' => $room->id,
        'exam_date' => '2026-07-10',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 30,
    ]);
    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => Unit::factory()->create()->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'expected_candidates' => 5,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);

    return [$slot, $campus, $semester];
}

function lecturerTimetable(Lecture $lecturer): array
{
    return app(LecturerTimetableService::class)->getTimetable($lecturer, [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-12',
    ]);
}

it('includes an assigned invigilation duty in the lecturer timetable + resource', function () {
    [$slot, $campus] = invigilationSlot();
    $lecturer = Lecture::factory()->create(['campus_id' => $campus->id]);

    ExamRoomSlotInvigilator::create([
        'exam_room_slot_id' => $slot->id,
        'lecture_id' => $lecturer->id,
        'role' => ExamRoomSlotInvigilator::ROLE_LEAD,
    ]);

    $output = lecturerTimetable($lecturer);

    expect($output['invigilation_duties'])->toHaveCount(1)
        ->and($output['invigilation_duties'][0]['role'])->toBe('lead')
        ->and($output['invigilation_duties'][0]['exam_date'])->toBe('2026-07-10')
        ->and($output['invigilation_duties'][0]['item_type'])->toBe('invigilation')
        ->and($output['invigilation_duties'][0]['session_count'])->toBe(1);

    // Resource must pass the new key through (it rebuilds with a fixed key set).
    $resourced = (new TimetableResource($output))->toArray(request());
    expect($resourced)->toHaveKey('invigilation_duties')
        ->and($resourced['invigilation_duties'])->toHaveCount(1);
});

it('does not include another lecturer invigilation duty', function () {
    [$slot, $campus] = invigilationSlot();
    $assigned = Lecture::factory()->create(['campus_id' => $campus->id]);
    $other = Lecture::factory()->create(['campus_id' => $campus->id]);

    ExamRoomSlotInvigilator::create([
        'exam_room_slot_id' => $slot->id,
        'lecture_id' => $assigned->id,
        'role' => ExamRoomSlotInvigilator::ROLE_LEAD,
    ]);

    expect(lecturerTimetable($other)['invigilation_duties'])->toBe([]);
});
