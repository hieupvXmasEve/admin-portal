<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Modules\Facilities\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->room = Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 30]);
    $this->lecture = Lecture::factory()->create(['campus_id' => $this->campus->id]);
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $this->semester->id,
    ]);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_exam_resit', 'manage_exam_schedule', 'schedule_exam_resit', 'complete_exam_resit']);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

function examSchedSlot(int $capacity = 30): ExamRoomSlot
{
    return ExamRoomSlot::factory()->create([
        'campus_id' => test()->campus->id,
        'room_id' => test()->room->id,
        'exam_date' => '2026-07-10',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => $capacity,
    ]);
}

function examSchedSession(ExamRoomSlot $slot, Unit $unit, int $expected = 5): ExamResitSession
{
    return ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'semester_id' => test()->semester->id,
        'campus_id' => $slot->campus_id,
        'expected_candidates' => $expected,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);
}

function examSchedPost(string $route, array|int $params, array $body): TestResponse
{
    return actingAs(test()->user)
        ->withSession(['current_campus_id' => test()->campus->id, '_token' => 'test-token'])
        ->post(route($route, $params), array_merge(['_token' => 'test-token'], $body));
}

it('renders the schedule management page', function () {
    examSchedSlot();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-schedule.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Schedule/Index')
            ->has('slots.data')
            ->has('rooms')
            ->has('units')
            ->has('lecturers')
            ->has('semesters'));
});

it('renders the schedule form for an attempt with matching sessions', function () {
    $unit = Unit::factory()->create();
    $slot = examSchedSlot();
    examSchedSession($slot, $unit);
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-resit.schedule.create', $attempt->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Schedule')
            ->has('attempt')
            ->has('sessions', 1));
});

it('renders the complete form for a scheduled attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-resit.complete.create', $attempt->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Complete')
            ->has('attempt'));
});

it('creates a room slot', function () {
    examSchedPost('academic.exam-schedule.room-slots.store', [], [
        'campus_id' => $this->campus->id,
        'room_id' => $this->room->id,
        'exam_date' => '2026-07-12',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ])->assertRedirect();

    expect(ExamRoomSlot::where('room_id', $this->room->id)->where('exam_date', '2026-07-12')->exists())->toBeTrue();
});

it('creates a unit-scoped session inside a slot', function () {
    $slot = examSchedSlot();
    $unit = Unit::factory()->create();

    examSchedPost('academic.exam-schedule.sessions.store', [], [
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'semester_id' => $this->semester->id,
        'expected_candidates' => 5,
    ])->assertRedirect();

    expect(ExamResitSession::where('exam_room_slot_id', $slot->id)->where('unit_id', $unit->id)->exists())->toBeTrue();
});

it('assigns an invigilator to a slot', function () {
    $slot = examSchedSlot();

    examSchedPost('academic.exam-schedule.invigilators.store', [], [
        'exam_room_slot_id' => $slot->id,
        'lecture_id' => $this->lecture->id,
        'role' => 'lead',
    ])->assertRedirect();

    expect(ExamRoomSlotInvigilator::where('exam_room_slot_id', $slot->id)->where('lecture_id', $this->lecture->id)->exists())->toBeTrue();
});

it('schedules an approved paid attempt into a matching session', function () {
    $unit = Unit::factory()->create();
    $slot = examSchedSlot();
    $session = examSchedSession($slot, $unit);
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    examSchedPost('academic.exam-resit.schedule.store', $attempt->id, [
        'exam_resit_session_id' => $session->id,
    ])->assertRedirect(route('academic.exam-resit.index'));

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_SCHEDULED);
});

it('surfaces a conflict when scheduling into a wrong-unit session', function () {
    $attemptUnit = Unit::factory()->create();
    $sessionUnit = Unit::factory()->create();
    $slot = examSchedSlot();
    $session = examSchedSession($slot, $sessionUnit);
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $attemptUnit,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    examSchedPost('academic.exam-resit.schedule.store', $attempt->id, [
        'exam_resit_session_id' => $session->id,
    ])->assertSessionHasErrors();

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_APPROVED);
});

it('records a resit result for a scheduled attempt', function () {
    $unit = Unit::factory()->create();
    $slot = examSchedSlot();
    $session = examSchedSession($slot, $unit);
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    examSchedPost('academic.exam-resit.complete.store', $attempt->id, [
        'resit_score' => 80,
    ])->assertRedirect(route('academic.exam-resit.index'));

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_COMPLETED)
        ->and($attempt->attempt_number)->toBe(1);
});
