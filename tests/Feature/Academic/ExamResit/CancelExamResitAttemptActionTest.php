<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\FinanceCharge;
use App\Models\PaymentApplication;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\CancelExamResitAttemptAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

function runCancelExamResit(int $attemptId, string $reason = 'Sinh viên xin rút', array $overrides = []): ExamResitAttempt
{
    return app(CancelExamResitAttemptAction::class)->run(array_merge([
        'attempt_id' => $attemptId,
        'reason' => $reason,
    ], $overrides));
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

function makeExamResitDng(Student $student, FinanceCharge $charge, string $status = DngPaymentRequest::STATUS_PUSHED_TO_DNG, bool $viaPivot = false): DngPaymentRequest
{
    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'fee_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'description' => 'Exam resit fee',
        'semester_id' => $charge->semester_id,
        'due_date' => now()->addDays(5),
        'item_id' => 'PTL-'.$charge->id.'-'.($viaPivot ? 'P' : 'D'),
        'amount' => $charge->amount,
        'status' => $status,
        'finance_charge_id' => $viaPivot ? null : $charge->id,
    ]);

    if ($viaPivot) {
        DngPaymentRequestCharge::create([
            'dng_payment_request_id' => $dng->id,
            'finance_charge_id' => $charge->id,
            'amount' => $charge->amount,
        ]);
    }

    return $dng;
}

it('cancels an approved hq_fee_pending attempt without a charge to void', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    $result = runCancelExamResit($attempt->id, 'Đổi sang học lại');

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($result->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED)
        ->and($result->cancelled_by_user_id)->toBe($this->user->id)
        ->and($result->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_NO_CHARGE)
        ->and($result->cancellation_notice_sent_at)->not->toBeNull()
        ->and($result->cancellation_notice_error)->toBeNull()
        ->and($result->cancellation_reason)->toBe('Đổi sang học lại')
        ->and($result->cancelled_at)->not->toBeNull()
        ->and($result->attempt_number)->toBeNull();

    expect(EmailLog::query()->where('recipient', $this->student->email)->exists())->toBeTrue();
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
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
    $directDng = makeExamResitDng($this->student, $charge);
    $pivotDng = makeExamResitDng($this->student, $charge, viaPivot: true);

    $otherCharge = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750000,
        'description' => 'Another exam resit fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $unrelatedDng = makeExamResitDng($this->student, $otherCharge);

    $result = runCancelExamResit($attempt->id, overrides: [
        'confirmation' => CancelExamResitAttemptAction::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($result->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED)
        ->and($result->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE)
        ->and($result->cancellation_notice_sent_at)->not->toBeNull();

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
    expect($directDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($pivotDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($unrelatedDng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('cancels a scheduled-but-unpaid attempt before sitting and releases the session assignment', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'scheduled_at' => now(),
    ]);
    $session = scheduledExamResitSessionForAttempt($attempt);
    $attempt->update(['exam_resit_session_id' => $session->id]);

    $result = runCancelExamResit($attempt->id, overrides: [
        'confirmation' => CancelExamResitAttemptAction::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($result->exam_resit_session_id)->toBeNull();

    expect($session->fresh()->actual_candidates)->toBe(0);
});

it('requires no-refund acknowledgement before cancelling a paid attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);
});

it('bridges linked paid dng evidence before cancelling a charge_created attempt', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);

    expect($charge->is_fully_paid)->toBeFalse();

    $paidDng = makeExamResitDng($this->student, $charge, DngPaymentRequest::STATUS_PAID_INVOICED);
    $paidDng->update([
        'dng_payment_id' => 'DNG-PTL-PAID-'.$paidDng->id,
        'paid_at' => now(),
    ]);

    $result = runCancelExamResit($attempt->id, overrides: [
        'acknowledge_no_refund' => true,
    ]);

    $charge->refresh();
    $paidDng->refresh();

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($result->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($result->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($charge->void_reason)->toBe('exam_resit_cancelled_paid_no_refund')
        ->and($paidDng->status)->toBe(DngPaymentRequest::STATUS_PAID_INVOICED)
        ->and($paidDng->payment_id)->not->toBeNull();

    $payment = $paidDng->payment()->firstOrFail();
    expect(PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe('0.00')
        ->and($payment->unapplied_amount)->toBe(750000.0);
});

it('cancels a paid scheduled attempt and releases the paid fee to unapplied credit without cancelling dng', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
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

    $attempt->refresh();
    $charge->refresh();
    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull()
        ->and($attempt->finance_charge_id)->toBe($charge->id)
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

    expect(PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe('0.00')
        ->and($payment->unapplied_amount)->toBe(750000.0)
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
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
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

    expect($session->fresh()->actual_candidates)->toBe(1);
});

it('rejects cancelling a terminal (completed) attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_COMPLETED,
        'attempt_number' => 1,
    ]);

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);
});
