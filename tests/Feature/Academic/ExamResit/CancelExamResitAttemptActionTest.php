<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\DispatchAcademicFinanceCancellationHandoffAction;
use App\Modules\Academic\Delivery\Actions\CancelExamResitAttemptAction;
use App\Modules\Academic\Delivery\Actions\CreateExamResitAttemptAction;
use App\Modules\Academic\Models\AcademicFinanceCancellationHandoff;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Actions\ProcessFinanceCancellationOperationAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Jobs\DispatchFinanceCancellationCompletionJob;
use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

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

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v1',
        'description' => 'Fixed resit fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function runCancelExamResit(int $attemptId, string $reason = 'Sinh viên xin rút', array $overrides = []): ExamResitAttempt
{
    $attempt = CancelExamResitAttemptAction::run(array_merge([
        'attempt_id' => $attemptId,
        'reason' => $reason,
    ], $overrides));

    // Durable handoff is afterCommit; under Queue::fake deliver it synchronously.
    AcademicFinanceCancellationHandoff::query()
        ->where('status', AcademicFinanceCancellationHandoff::STATUS_PENDING)
        ->orderBy('id')
        ->each(fn (AcademicFinanceCancellationHandoff $handoff) => DispatchAcademicFinanceCancellationHandoffAction::run([
            'handoff_id' => $handoff->id,
        ]));

    return $attempt->fresh() ?? $attempt;
}

function scheduledExamResitSessionForAttempt(ExamResitAttempt $attempt): ExamResitSession
{
    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $attempt->campus_id,
        'room_id' => Room::factory()->create(['campus_id' => $attempt->campus_id])->id,
    ]);

    return ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $attempt->unit_id,
        'semester_id' => $attempt->operation_semester_id,
        'campus_id' => $attempt->campus_id,
        'expected_candidates' => 5,
        'actual_candidates' => 1,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);
}

function examResitCancellationChargeForAttempt(ExamResitAttempt $attempt): FinanceCharge
{
    $obligationId = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->where('obligation_type', AcademicFinanceObligationSource::EXAM_RESIT_FEE)
        ->value('id');

    return FinanceCharge::query()
        ->where('finance_obligation_id', $obligationId)
        ->firstOrFail();
}

function makeExamResitDng(
    Student $student,
    FinanceCharge $charge,
    string $status = DngPaymentRequest::STATUS_PENDING,
): DngPaymentRequest {
    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'fee_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'description' => 'Exam resit fee',
        'semester_id' => $charge->semester_id,
        'due_date' => now()->addDays(5),
        'item_id' => 'PTL-'.$charge->id,
        'amount' => $charge->amount,
        'status' => $status,
    ]);

    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dng->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    return $dng;
}

function settleExamResitFinanceCancellation(ExamResitAttempt $attempt): void
{
    $operation = FinanceCancellationOperation::query()
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->firstOrFail();

    app(ProcessFinanceCancellationOperationAction::class)->handle($operation->id);

    FinanceCancellationCompletionOutbox::query()
        ->where('finance_cancellation_operation_id', $operation->id)
        ->where('status', FinanceCancellationCompletionOutbox::STATUS_PENDING)
        ->orderBy('event_version')
        ->each(function (FinanceCancellationCompletionOutbox $outbox): void {
            app(DispatchFinanceCancellationCompletionJob::class, ['outboxId' => $outbox->id])
                ->handle(app(FinanceCancellationCompletionContract::class));
        });
}

it('requests finance cancellation and completes only through the durable outbox', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    $result = runCancelExamResit($attempt->id, 'Đổi sang học lại');

    expect($result->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION)
        ->and($result->cancelled_by_user_id)->toBe($this->user->id)
        ->and($result->cancellation_reason)->toBe('Đổi sang học lại')
        ->and($result->cancelled_at)->toBeNull()
        ->and($result->attempt_number)->toBeNull();

    settleExamResitFinanceCancellation($attempt);
    $attempt->refresh();

    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED)
        ->and($attempt->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_NO_CHARGE)
        ->and($attempt->cancellation_notice_sent_at)->not->toBeNull()
        ->and($attempt->cancellation_notice_error)->toBeNull()
        ->and($attempt->cancelled_at)->not->toBeNull();

    expect(NotificationEventOutbox::query()
        ->where('event_name', 'notification.external_email_requested')
        ->where('aggregate_type', 'exam_resit_attempt')
        ->exists())->toBeTrue();
});

it('cancels a target-path charge-created attempt through the finance cancellation operation', function () {
    Queue::fake();

    $unit = Unit::factory()->create();
    $syllabus = SyllabusTemplate::create([
        'unit_id' => $unit->id,
        'title' => 'Exam resit cancellation policy test syllabus',
        'version' => '1.0',
        'total_hours' => 120,
        'total_sessions' => 30,
        'learning_outcomes' => ['Complete the unit outcomes'],
        'grading_criteria' => [['name' => 'Final Exam', 'weight' => 100]],
        'required_materials' => [],
        'is_default' => true,
        'is_active' => true,
        'created_by' => $this->user->id,
        'exam_resit_fee' => 750_000,
        'exam_resit_max_attempts' => 1,
        'exam_resit_late_payment_grace_days' => 14,
        'exam_resit_allow_unpaid_sitting' => false,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
        'syllabus_template_id' => $syllabus->id,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'override_pass' => false,
        'final_percentage' => 48,
        'attendance_percentage' => 95,
        'meets_attendance_requirement' => true,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
        'failure_reason_snapshot' => [
            'attendance_evidence_state' => 'recorded',
        ],
    ]);

    $attempt = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
    $obligation = FinanceObligation::query()
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();

    $result = runCancelExamResit($attempt->id, overrides: [
        'confirmation' => CancelExamResitAttemptAction::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION);

    settleExamResitFinanceCancellation($attempt);
    $attempt->refresh();

    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED)
        ->and($attempt->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->fresh()->void_reason)->toBe('exam_resit_cancelled')
        ->and($obligation->fresh()->lifecycle_status)->toBe(FinanceObligation::STATUS_VOIDED);
});

it('requires confirmation before cancelling an unpaid charge_created attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);
});

it('voids only the linked finance charge and linked awaiting dng when cancelling an unpaid charge_created attempt', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();

    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);
    $charge = examResitCancellationChargeForAttempt($attempt);
    $firstDng = makeExamResitDng($this->student, $charge);
    $secondDng = makeExamResitDng($this->student, $charge);

    $otherCharge = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750000,
        'description' => 'Another exam resit fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $unrelatedDng = makeExamResitDng($this->student, $otherCharge, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    $result = runCancelExamResit($attempt->id, overrides: [
        'confirmation' => CancelExamResitAttemptAction::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION);

    settleExamResitFinanceCancellation($attempt);
    $attempt->refresh();

    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED)
        ->and($attempt->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE)
        ->and($attempt->cancellation_notice_sent_at)->not->toBeNull();

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
    expect($firstDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($secondDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($unrelatedDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('cancels a scheduled-but-unpaid attempt before sitting and releases the session assignment', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
        'scheduled_at' => now(),
    ]);
    $session = scheduledExamResitSessionForAttempt($attempt);
    $attempt->update(['exam_resit_session_id' => $session->id]);

    $result = runCancelExamResit($attempt->id);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION)
        ->and($result->exam_resit_session_id)->toBe($session->id);

    settleExamResitFinanceCancellation($attempt);
    $attempt->refresh();

    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->exam_resit_session_id)->toBeNull();

    expect($session->fresh()->actual_candidates)->toBe(0);
});

it('requires no-refund acknowledgement before cancelling a paid attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = examResitCancellationChargeForAttempt($attempt);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);
});

it('bridges linked paid dng evidence before cancelling a charge_created attempt', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = examResitCancellationChargeForAttempt($attempt);

    expect($charge->is_fully_paid)->toBeFalse();

    $paidDng = makeExamResitDng($this->student, $charge, DngPaymentRequest::STATUS_PAID_INVOICED);
    $paidDng->update([
        'dng_payment_id' => 'DNG-PTL-PAID-'.$paidDng->id,
        'paid_at' => now(),
    ]);

    $result = runCancelExamResit($attempt->id, overrides: [
        'acknowledge_no_refund' => true,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION);

    settleExamResitFinanceCancellation($attempt);

    $attempt->refresh();
    $charge->refresh();
    $paidDng->refresh();

    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->void_reason)->toBe('exam_resit_cancelled_paid_no_refund')
        ->and($paidDng->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED)
        ->and($paidDng->payment_id)->not->toBeNull();

    $payment = $paidDng->payment()->firstOrFail();
    expect((float) PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe(0.0)
        ->and((float) $payment->unapplied_amount)->toBe(750000.0);
});

it('cancels a paid scheduled attempt and releases the paid fee to unapplied credit without cancelling dng', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = examResitCancellationChargeForAttempt($attempt);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();
    $session = scheduledExamResitSessionForAttempt($attempt);
    $attempt->update([
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
    ]);

    $paidDng = makeExamResitDng($this->student, $charge, DngPaymentRequest::STATUS_PAID_INVOICED);

    $result = runCancelExamResit($attempt->id, overrides: [
        'acknowledge_no_refund' => true,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION);

    settleExamResitFinanceCancellation($attempt);

    $attempt->refresh();
    $charge->refresh();
    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull()
        ->and($attempt->exam_resit_session_id)->toBeNull()
        ->and($attempt->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND)
        ->and($attempt->cancellation_notice_sent_at)->not->toBeNull()
        ->and($charge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->void_reason)->toBe('exam_resit_cancelled_paid_no_refund')
        ->and($paidDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED);

    $payment = PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->where('entry_type', 'application')
        ->firstOrFail()
        ->payment()
        ->firstOrFail();

    expect((float) PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe(0.0)
        ->and((float) $payment->unapplied_amount)->toBe(750000.0)
        ->and($session->fresh()->actual_candidates)->toBe(0);
});

it('refreshes session candidate count from non-cancelled attempts after cancelling one scheduled attempt', function () {
    Queue::fake();
    $unit = Unit::factory()->create();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
    ]);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = examResitCancellationChargeForAttempt($attempt);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();
    $session = scheduledExamResitSessionForAttempt($attempt);
    $session->update([
        'status' => ExamResitSession::STATUS_COMPLETED,
        'expected_candidates' => 2,
        'actual_candidates' => 2,
        'completed_at' => now(),
    ]);
    $attempt->update([
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
    ]);

    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'status' => ExamResitAttempt::STATUS_COMPLETED,
        'exam_resit_session_id' => $session->id,
        'attempt_number' => 1,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'completed_at' => now(),
    ]);

    runCancelExamResit($attempt->id, overrides: [
        'acknowledge_no_refund' => true,
    ]);
    settleExamResitFinanceCancellation($attempt);

    expect($session->fresh()->actual_candidates)->toBe(1);
});

it('rejects cancelling a terminal (completed) attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_COMPLETED,
        'attempt_number' => 1,
    ]);

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);
});
