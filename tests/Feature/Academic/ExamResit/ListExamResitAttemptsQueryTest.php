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
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
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
        ->and($rows['ready_to_schedule']['available_actions'])->toContain('cancel')
        ->and($rows['ready_to_schedule']['cancel_context']['requires_no_refund_acknowledgement'])->toBeTrue();

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

it('treats linked paid dng evidence as paid in the cancellation context before bridge', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $charge = FinanceCharge::query()
        ->where('finance_obligation_id', FinanceObligation::query()
            ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
            ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
            ->value('id'))
        ->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $this->student->id,
        'campus_code' => 'TEST',
        'student_code' => $this->student->student_id,
        'fee_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'description' => 'Exam resit fee',
        'semester_id' => $charge->semester_id,
        'due_date' => now()->addDays(5),
        'item_id' => 'PTL-LIST-'.$charge->id,
        'amount' => $charge->amount,
        'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        'dng_payment_id' => 'DNG-PTL-LIST-'.$charge->id,
        'paid_at' => now(),
    ]);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dng->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    $result = listExamResit();
    $row = $result['attempts']->items()[0];

    expect($row['payment_state']['value'])->toBe('paid')
        ->and($row['operation_state']['value'])->toBe('ready_to_schedule')
        ->and($row['cancel_context']['fee_state'])->toBe('paid_no_refund')
        ->and($row['cancel_context']['requires_no_refund_acknowledgement'])->toBeTrue()
        ->and($row['cancel_context']['requires_unpaid_fee_confirmation'])->toBeFalse()
        ->and($result['summary']['ready_to_schedule'])->toBe(1)
        ->and($result['summary']['awaiting_payment'])->toBe(0)
        ->and($dng->fresh()->payment_id)->toBeNull();
});
