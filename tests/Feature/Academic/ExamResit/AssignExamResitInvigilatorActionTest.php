<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Modules\Facilities\Models\Room;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\AssignExamResitInvigilatorAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->room1 = Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 30]);
    $this->room2 = Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 30]);
    $this->lecture = Lecture::factory()->create(['campus_id' => $this->campus->id]);

    $this->slot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room1->id,
        'exam_date' => '2026-07-01',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
});

function runAssignInvigilator(array $overrides = []): ExamRoomSlotInvigilator
{
    return app(AssignExamResitInvigilatorAction::class)->run(array_merge([
        'exam_room_slot_id' => test()->slot->id,
        'lecture_id' => test()->lecture->id,
    ], $overrides));
}

it('assigns a lecturer as an invigilator with the default assistant role', function () {
    $invigilator = runAssignInvigilator();

    expect($invigilator->exam_room_slot_id)->toBe($this->slot->id)
        ->and($invigilator->lecture_id)->toBe($this->lecture->id)
        ->and($invigilator->role)->toBe(ExamRoomSlotInvigilator::ROLE_ASSISTANT)
        ->and($invigilator->assigned_by_user_id)->toBe($this->user->id);
});

it('assigns a lead invigilator role', function () {
    $invigilator = runAssignInvigilator(['role' => ExamRoomSlotInvigilator::ROLE_LEAD]);

    expect($invigilator->role)->toBe(ExamRoomSlotInvigilator::ROLE_LEAD);
});

it('rejects an invalid invigilator role', function () {
    runAssignInvigilator(['role' => 'observer']);
})->throws(ValidationException::class);

it('rejects assigning the same lecturer twice to the same slot', function () {
    runAssignInvigilator();

    runAssignInvigilator();
})->throws(ValidationException::class);

it('rejects assigning an invigilator to a cancelled slot', function () {
    $this->slot->update(['status' => ExamRoomSlot::STATUS_CANCELLED]);

    runAssignInvigilator();
})->throws(ValidationException::class);

it('rejects an invigilator teaching a class at an overlapping time', function () {
    ClassSession::factory()->create([
        'course_offering_id' => CourseOffering::factory()->create(['campus_id' => $this->campus->id]),
        'lecture_id' => $this->lecture->id,
        'room_id' => $this->room2->id,
        'session_date' => '2026-07-01',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
    ]);

    runAssignInvigilator();
})->throws(ValidationException::class);

it('rejects an invigilator already invigilating another overlapping slot', function () {
    $otherSlot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room2->id,
        'exam_date' => '2026-07-01',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    app(AssignExamResitInvigilatorAction::class)->run([
        'exam_room_slot_id' => $otherSlot->id,
        'lecture_id' => $this->lecture->id,
    ]);

    runAssignInvigilator();
})->throws(ValidationException::class);

it('allows the same lecturer to invigilate a non-overlapping slot', function () {
    $laterSlot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room2->id,
        'exam_date' => '2026-07-01',
        'start_time' => '11:00:00',
        'end_time' => '13:00:00',
    ]);

    runAssignInvigilator();
    $second = app(AssignExamResitInvigilatorAction::class)->run([
        'exam_room_slot_id' => $laterSlot->id,
        'lecture_id' => $this->lecture->id,
    ]);

    expect($second->id)->not->toBeNull();
});
