<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Queries\ListExamResitAttemptsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
});

function scheduledSessionFor(Unit $unit): ExamResitSession
{
    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => test()->campus->id,
        'room_id' => Room::factory()->create(['campus_id' => test()->campus->id])->id,
    ]);

    return ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'semester_id' => test()->semester->id,
        'campus_id' => test()->campus->id,
        'expected_candidates' => 5,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);
}

function listExamResit(array $filters = []): array
{
    return app(ListExamResitAttemptsQuery::class)->handle(array_merge([
        'search' => '',
        'status' => null,
        'operation_state' => null,
        'semester_id' => null,
        'unit_id' => null,
        'sort' => null,
        'direction' => null,
        'per_page' => 15,
    ], $filters), test()->campus->id);
}

it('derives payment, operation state and actions across the lifecycle and counts a summary', function () {
    // A: approved + paid → ready_to_schedule
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);
    // B: approved + unpaid charge_created → awaiting_payment
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
    ]);
    // C: scheduled + paid → scheduled
    $sessionUnit = Unit::factory()->create();
    $session = scheduledSessionFor($sessionUnit);
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $sessionUnit,
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);
    // D: completed
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_COMPLETED,
        'attempt_number' => 1,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
        'final_chosen_score' => 72,
    ]);
    // E: cancelled
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_CANCELLED,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CANCELLED,
        'cancelled_at' => now(),
    ]);

    $result = listExamResit();
    $summary = $result['summary'];

    expect($summary['total'])->toBe(5)
        ->and($summary['awaiting_payment'])->toBe(1)
        ->and($summary['ready_to_schedule'])->toBe(1)
        ->and($summary['scheduled'])->toBe(1)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['cancelled'])->toBe(1);

    $rows = collect($result['attempts']->items())->keyBy(fn ($r) => $r['operation_state']['value']);

    expect($rows['ready_to_schedule']['payment_state']['value'])->toBe('paid')
        ->and($rows['ready_to_schedule']['available_actions'])->toContain('schedule')
        ->and($rows['ready_to_schedule']['available_actions'])->not->toContain('cancel');

    expect($rows['awaiting_payment']['payment_state']['value'])->toBe('awaiting_payment')
        ->and($rows['awaiting_payment']['available_actions'])->toContain('cancel')
        ->and($rows['awaiting_payment']['available_actions'])->toContain('schedule');

    expect($rows['scheduled']['available_actions'])->toContain('complete')
        ->and($rows['scheduled']['schedule_state']['value'])->toBe('scheduled');

    expect($rows['completed']['result_state']['value'])->toBe('has_result')
        ->and($rows['completed']['available_actions'])->toBe([]);
});

it('scopes rows to the active campus', function () {
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    $otherCampus = Campus::factory()->create();
    $otherStudent = Student::factory()->forCampus($otherCampus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $this->semester->id,
    ]);
    makeApprovedExamResitAttempt($otherStudent, $otherCampus, $this->semester);

    $result = listExamResit();

    expect($result['summary']['total'])->toBe(1)
        ->and($result['attempts']->total())->toBe(1);
});

it('filters by derived operation_state', function () {
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID, 'paid_at' => now(),
    ]);
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
    ]);

    $result = listExamResit(['operation_state' => 'awaiting_payment']);

    expect($result['attempts']->total())->toBe(1)
        ->and($result['attempts']->items()[0]['operation_state']['value'])->toBe('awaiting_payment');
});
