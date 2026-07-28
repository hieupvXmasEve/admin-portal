<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CreateExamResitSessionAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->room = Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 40]);
    $this->slot = ExamRoomSlot::factory()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'capacity' => 30,
    ]);
});

function runCreateExamResitSession(array $overrides = []): ExamResitSession
{
    return app(CreateExamResitSessionAction::class)->run(array_merge([
        'exam_room_slot_id' => test()->slot->id,
        'unit_id' => Unit::factory()->create()->id,
        'semester_id' => test()->semester->id,
        'expected_candidates' => 10,
    ], $overrides));
}

it('creates a unit-scoped session inside a scheduled room slot', function () {
    $unit = Unit::factory()->create();

    $session = runCreateExamResitSession(['unit_id' => $unit->id, 'expected_candidates' => 12]);

    expect($session->status)->toBe(ExamResitSession::STATUS_SCHEDULED)
        ->and($session->exam_room_slot_id)->toBe($this->slot->id)
        ->and($session->unit_id)->toBe($unit->id)
        ->and($session->semester_id)->toBe($this->semester->id)
        ->and($session->campus_id)->toBe($this->campus->id)
        ->and($session->expected_candidates)->toBe(12)
        ->and($session->scheduled_by_user_id)->toBe($this->user->id);
});

it('lets two sessions for different units share one room slot', function () {
    $first = runCreateExamResitSession(['unit_id' => Unit::factory()->create()->id, 'expected_candidates' => 10]);
    $second = runCreateExamResitSession(['unit_id' => Unit::factory()->create()->id, 'expected_candidates' => 15]);

    expect($first->exam_room_slot_id)->toBe($this->slot->id)
        ->and($second->exam_room_slot_id)->toBe($this->slot->id)
        ->and($first->unit_id)->not->toBe($second->unit_id);
});

it('rejects total expected candidates exceeding the slot capacity', function () {
    // Slot capacity is 30; 20 + 15 = 35 overflows.
    runCreateExamResitSession(['expected_candidates' => 20]);

    runCreateExamResitSession(['expected_candidates' => 15]);
})->throws(ValidationException::class);

it('rejects creating a session with zero planned candidates', function () {
    // A session must plan at least one seat, otherwise no attempt could ever be
    // assigned to it (assignment is capped by expected_candidates).
    runCreateExamResitSession(['expected_candidates' => 0]);
})->throws(ValidationException::class);

it('rejects creating a session in a cancelled room slot', function () {
    $cancelled = ExamRoomSlot::factory()->cancelled()->create([
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
    ]);

    runCreateExamResitSession(['exam_room_slot_id' => $cancelled->id]);
})->throws(ValidationException::class);

it('derives campus from the room slot regardless of unit campus', function () {
    $session = runCreateExamResitSession();

    expect($session->campus_id)->toBe($this->slot->campus_id);
});
