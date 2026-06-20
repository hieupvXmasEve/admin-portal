<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\CancelExamResitAttemptAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
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

function runCancelExamResit(int $attemptId, string $reason = 'Sinh viên xin rút'): ExamResitAttempt
{
    return app(CancelExamResitAttemptAction::class)->run([
        'attempt_id' => $attemptId,
        'reason' => $reason,
    ]);
}

it('cancels an approved hq_fee_pending attempt without a charge to void', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    $result = runCancelExamResit($attempt->id, 'Đổi sang học lại');

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($result->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED)
        ->and($result->cancellation_reason)->toBe('Đổi sang học lại')
        ->and($result->cancelled_at)->not->toBeNull()
        ->and($result->attempt_number)->toBeNull();
});

it('voids the linked finance charge when cancelling an unpaid charge_created attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();

    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);
    $chargeId = $attempt->finance_charge_id;

    $result = runCancelExamResit($attempt->id);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($result->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CANCELLED);

    $charge = FinanceCharge::findOrFail($chargeId);
    expect($charge->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('cancels a scheduled-but-unpaid attempt before sitting', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'scheduled_at' => now(),
    ]);

    $result = runCancelExamResit($attempt->id);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_CANCELLED);
});

it('blocks cancelling a paid attempt and preserves payment evidence', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);

    $attempt->refresh();
    $charge->refresh();
    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_APPROVED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull()
        ->and($attempt->finance_charge_id)->toBe($charge->id)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('rejects cancelling a terminal (completed) attempt', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'status' => ExamResitAttempt::STATUS_COMPLETED,
        'attempt_number' => 1,
    ]);

    expect(fn () => runCancelExamResit($attempt->id))->toThrow(RuntimeException::class);
});
