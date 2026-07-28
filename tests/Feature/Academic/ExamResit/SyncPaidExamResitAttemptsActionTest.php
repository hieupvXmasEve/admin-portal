<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\SyncPaidExamResitAttemptsAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\AllocatePaymentAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
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

/**
 * Build an exam-resit attempt that already has an HQ charge (charge_created),
 * mirroring the real HQ handoff path.
 */
function chargedExamResitAttempt(): ExamResitAttempt
{
    $attempt = makeApprovedExamResitAttempt(test()->student, test()->campus, test()->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    return $attempt->fresh();
}

function examResitChargeForAttempt(ExamResitAttempt $attempt): FinanceCharge
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

it('marks a fully paid exam-resit attempt as paid from Finance evidence', function () {
    $attempt = chargedExamResitAttempt();
    $charge = examResitChargeForAttempt($attempt);
    payExamResitChargeFully($charge);

    $result = app(SyncPaidExamResitAttemptsAction::class)->runForStudent($this->student->id);

    $attempt->refresh();

    expect($result['checked'])->toBe(1)
        ->and($result['eligible'])->toBe(1)
        ->and($result['synced'])->toBe(1)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull();
});

it('skips an attempt whose charge is not fully paid', function () {
    $attempt = chargedExamResitAttempt();

    $result = app(SyncPaidExamResitAttemptsAction::class)->runForStudent($this->student->id);

    $attempt->refresh();

    expect($result['checked'])->toBe(1)
        ->and($result['eligible'])->toBe(0)
        ->and($result['synced'])->toBe(0)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
        ->and($attempt->paid_at)->toBeNull();
});

it('skips an attempt whose charge has been voided even if a payment exists', function () {
    $attempt = chargedExamResitAttempt();
    $charge = examResitChargeForAttempt($attempt);
    payExamResitChargeFully($charge);
    $charge->void($this->user->id, 'cancelled');

    $result = app(SyncPaidExamResitAttemptsAction::class)->runForStudent($this->student->id);

    $attempt->refresh();

    expect($result['synced'])->toBe(0)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);
});

it('targets paid attempts by student through canonical settlement evidence', function () {
    $attempt = chargedExamResitAttempt();
    $charge = examResitChargeForAttempt($attempt);
    payExamResitChargeFully($charge);

    $result = app(ExamResitAttemptPaymentSyncer::class)->runForStudent($this->student->id);

    $attempt->refresh();

    expect($result['synced'])->toBe(1)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID);
});

it('flips the attempt to paid automatically when a payment is allocated to its charge', function () {
    $attempt = chargedExamResitAttempt();
    $charge = examResitChargeForAttempt($attempt);
    $line = InvoiceLine::where('charge_id', $charge->id)->where('status', 'active')->firstOrFail();

    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => $charge->amount,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'external_ref' => 'DNG-PTL-PAID',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    app(AllocatePaymentAction::class)->run($payment, $charge, (float) $charge->amount, $this->user->id);

    $attempt->refresh();

    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull();
});

it('does not mutate the attempt during a dry run', function () {
    $attempt = chargedExamResitAttempt();
    $charge = examResitChargeForAttempt($attempt);
    payExamResitChargeFully($charge);

    $result = app(SyncPaidExamResitAttemptsAction::class)->runForStudent($this->student->id, dryRun: true);

    $attempt->refresh();

    expect($result['eligible'])->toBe(1)
        ->and($result['synced'])->toBe(0)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
        ->and($attempt->paid_at)->toBeNull();
});
