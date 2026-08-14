<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Modules\Facilities\Models\Room;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Queries\ListExamRoomSlotsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
});

function slotWithSessionAndInvigilator(Campus $campus, Semester $semester): ExamRoomSlot
{
    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $campus->id,
        'room_id' => Room::factory()->create(['campus_id' => $campus->id, 'capacity' => 40])->id,
        'capacity' => 40,
    ]);
    ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => Unit::factory()->create()->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'expected_candidates' => 5,
        'actual_candidates' => 2,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);
    ExamRoomSlotInvigilator::create([
        'exam_room_slot_id' => $slot->id,
        'lecture_id' => Lecture::factory()->create(['campus_id' => $campus->id])->id,
        'role' => ExamRoomSlotInvigilator::ROLE_LEAD,
        'assigned_by_user_id' => test()->user->id,
    ]);

    return $slot;
}

it('lists campus room slots with sessions, invigilators and seat usage', function () {
    slotWithSessionAndInvigilator($this->campus, $this->semester);

    $result = app(ListExamRoomSlotsQuery::class)->handle(['per_page' => 15], $this->campus->id);
    $rows = $result['slots']->items();

    expect($rows)->toHaveCount(1);
    $row = $rows[0];
    expect($row['seats_total'])->toBe(40)
        ->and($row['seats_used'])->toBe(5)
        ->and($row['seats_assigned'])->toBe(2)
        ->and($row['sessions'])->toHaveCount(1)
        ->and($row['invigilators'])->toHaveCount(1)
        ->and($row['invigilators'][0]['role'])->toBe(ExamRoomSlotInvigilator::ROLE_LEAD);
});

it('scopes slots to the active campus', function () {
    slotWithSessionAndInvigilator($this->campus, $this->semester);
    $other = Campus::factory()->create();
    slotWithSessionAndInvigilator($other, $this->semester);

    $result = app(ListExamRoomSlotsQuery::class)->handle(['per_page' => 15], $this->campus->id);

    expect($result['slots']->total())->toBe(1);
});
