<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

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

it('creates one exam_resit_fee charge linked to the attempt and transitions it to charge_created', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'fee_amount' => 750_000,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle([
        'attempt_id' => $attempt->id,
    ]);

    $attempt->refresh();
    $charges = FinanceCharge::query()
        ->where('source_type', ExamResitAttempt::class)
        ->where('source_id', $attempt->id)
        ->get();

    expect($charges)->toHaveCount(1);
    $charge = $charges->first();

    expect($charge->charge_type)->toBe(FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->and((float) $charge->amount)->toBe(750_000.0)
        ->and($charge->semester_id)->toBe($this->semester->id)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
        ->and($attempt->finance_charge_id)->toBe($charge->id)
        ->and($attempt->charge_created_by_user_id)->toBe($this->user->id)
        ->and($attempt->charge_created_at)->not->toBeNull()
        ->and($attempt->status)->toBe(ExamResitAttempt::STATUS_APPROVED);
});

it('bills the charge on the charge_semester, distinct from the operation semester', function () {
    $chargeSemester = Semester::factory()->create();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $chargeSemester->id,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $charge = FinanceCharge::query()
        ->where('source_type', ExamResitAttempt::class)
        ->where('source_id', $attempt->id)
        ->firstOrFail();

    expect($charge->semester_id)->toBe($chargeSemester->id);
});

it('reuses an existing active source charge instead of creating a duplicate', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    $existing = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750_000,
        'description' => 'Pre-existing exam resit charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => ExamResitAttempt::class,
        'source_id' => $attempt->id,
        'created_by_user_id' => $this->user->id,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $attempt->refresh();

    expect(FinanceCharge::query()
        ->where('source_type', ExamResitAttempt::class)
        ->where('source_id', $attempt->id)
        ->count())->toBe(1)
        ->and($attempt->finance_charge_id)->toBe($existing->id)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);
});

it('rejects charge creation when the attempt is not waiting for HQ fee creation', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
})->throws(ValidationException::class);
