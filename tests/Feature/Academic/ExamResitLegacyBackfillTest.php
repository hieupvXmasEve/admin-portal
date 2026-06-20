<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\CreateLegacyExamResitChargeFromPaidPtlAction;
use App\Modules\Academic\Actions\ReconcileLegacyExamResitFeesAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * ACAD-RET-001 Slice 6 — legacy `exam_resit_fee` backfill / reconciliation.
 *
 * A legacy charge is an active `exam_resit_fee` FinanceCharge that predates the
 * Academic exam-resit source contract, so it carries no ExamResitAttempt link.
 * Reconciliation links each safely matchable charge to a new legacy ExamResitAttempt
 * source and reports the rest as exceptions instead of guessing.
 */
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
 * A failed, finalized academic record that is eligible for exam resit
 * (grade-fail lane, attendance evidence recorded).
 *
 * @param  array<string, mixed>  $overrides
 */
function resitUnit(array $overrides = []): Unit
{
    return Unit::factory()->create(array_merge(['code' => 'TEC002'], $overrides));
}

function failedGradeRecord(Unit $unit, array $overrides = []): AcademicRecord
{
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
    ]);

    return AcademicRecord::factory()->create(array_merge([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
        'total_not_recorded' => 0,
    ], $overrides));
}

/**
 * A legacy `exam_resit_fee` charge with NO Academic source (source_type/id null),
 * created through the normal Finance charge path so it has a payable invoice line.
 *
 * @param  array<string, mixed>  $opts
 */
function legacyExamResitCharge(array $opts = []): FinanceCharge
{
    return app(CreateFinanceChargeAction::class)->handle([
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => $opts['amount'] ?? 750_000,
        'description' => $opts['description'] ?? 'Phí thi lại (legacy import)',
    ]);
}

function paidPtlDngRequest(array $opts = []): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => test()->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU'.test()->student->id,
        'item_id' => $opts['item_id'] ?? 'PTL-'.test()->student->id,
        'fee_type' => 'PTL',
        'description' => $opts['description'] ?? 'Exam Retake Fee: TEC001',
        'semester_id' => test()->semester->id,
        'amount' => $opts['amount'] ?? 3_000_000,
        'status' => $opts['status'] ?? DngPaymentRequest::STATUS_PAID_INVOICED,
        'finance_charge_id' => $opts['finance_charge_id'] ?? null,
    ]);
}

it('reconciles a paid legacy charge into a paid legacy-linked exam-resit attempt', function () {
    $unit = resitUnit();
    $record = failedGradeRecord($unit);
    $charge = legacyExamResitCharge();
    payExamResitChargeFully($charge);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(1)
        ->and($result['reconciled'])->toBe(1)
        ->and($result['exceptions'])->toBe(0);

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->academic_record_id)->toBe($record->id)
        ->and($attempt->unit_id)->toBe($unit->id)
        ->and($attempt->student_id)->toBe($this->student->id)
        ->and($attempt->original_semester_id)->toBe($record->semester_id)
        ->and($attempt->charge_semester_id)->toBe($charge->semester_id)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull()
        ->and((float) $attempt->fee_amount)->toBe(750_000.0)
        ->and($attempt->policy_snapshot['legacy_backfill'] ?? null)->toBeTrue();

    // The charge is repointed to the new Academic source so future idempotency and
    // Fee Monitor inference see a real exam-resit source.
    $charge->refresh();
    expect($charge->source_type)->toBe(ExamResitAttempt::class)
        ->and($charge->source_id)->toBe($attempt->id);
});

it('reconciles an unpaid legacy charge as charge_created (payment evidence derived)', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
        ->and($attempt->paid_at)->toBeNull();
});

it('reports an exception when the student has no eligible failed record', function () {
    // No failed record at all (e.g. the student already passed the resit unit).
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(1)
        ->and($result['reconciled'])->toBe(0)
        ->and($result['exceptions'])->toBe(1);

    expect(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeFalse();

    $exception = collect($result['details'])->firstWhere('charge_id', $charge->id);
    expect($exception['status'])->toBe('exception')
        ->and($exception['reason'])->toBe('no_eligible_failed_record');
});

it('reports an exception when the only failed record is an attendance failure (resit-ineligible)', function () {
    $unit = resitUnit();
    failedGradeRecord($unit, ['failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED]);
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['exceptions'])->toBe(1)
        ->and($result['reconciled'])->toBe(0)
        ->and(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeFalse();
});

it('reports an exception when failed records are not resit units (TEC001/TEC002)', function () {
    failedGradeRecord(Unit::factory()->create(['code' => 'BUS101']));
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại (no unit named)']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['exceptions'])->toBe(1)
        ->and($result['reconciled'])->toBe(0);

    $exception = collect($result['details'])->firstWhere('charge_id', $charge->id);
    expect($exception['reason'])->toBe('no_eligible_failed_record');
});

it('disambiguates multiple eligible units by unit code in the charge description', function () {
    $target = Unit::factory()->create(['code' => 'TEC002']);
    failedGradeRecord($target);
    failedGradeRecord(Unit::factory()->create(['code' => 'TEC001']));
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại: TEC002 - Technical Module 2']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($result['exceptions'])->toBe(0)
        ->and($attempt->unit_id)->toBe($target->id);
});

it('does not touch charges already linked to an Academic exam-resit source', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(0)
        ->and($result['reconciled'])->toBe(0)
        ->and(ExamResitAttempt::query()->count())->toBe(1);
});

it('skips a voided legacy charge', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();
    $charge->void($this->user->id, 'cancelled');

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(0)
        ->and($result['reconciled'])->toBe(0);
});

it('writes nothing during a dry run', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run(dryRun: true);

    expect($result['reconciled'])->toBe(1);

    $charge->refresh();
    expect(ExamResitAttempt::query()->count())->toBe(0)
        ->and($charge->source_type)->toBeNull();
});

it('is idempotent — a second run creates no further attempts', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    legacyExamResitCharge();

    $first = app(ReconcileLegacyExamResitFeesAction::class)->run();
    $second = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($first['reconciled'])->toBe(1)
        ->and($second['checked'])->toBe(0)
        ->and($second['reconciled'])->toBe(0)
        ->and(ExamResitAttempt::query()->count())->toBe(1);
});

it('runs end-to-end through the artisan command', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();

    $this->artisan('academic:reconcile-legacy-exam-resit-fees')
        ->assertExitCode(0);

    expect(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeTrue();
});

it('the artisan command --dry-run writes nothing', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    legacyExamResitCharge();

    $this->artisan('academic:reconcile-legacy-exam-resit-fees', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(ExamResitAttempt::query()->count())->toBe(0);
});

it('reconciles a passed backfilled TEC002 record when the student already completed the resit', function () {
    $unit = resitUnit();
    $record = failedGradeRecord($unit, [
        'is_passed' => true,
        'completion_status' => 'completed',
    ]);
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại (legacy import)']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->academic_record_id)->toBe($record->id)
        ->and($attempt->unit_id)->toBe($unit->id);
});

it('defaults to TEC002 when the student studied both resit units', function () {
    failedGradeRecord(Unit::factory()->create(['code' => 'TEC001']));
    $tec002 = resitUnit();
    $target = failedGradeRecord($tec002);
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại (no unit named)']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->academic_record_id)->toBe($target->id)
        ->and($attempt->unit_id)->toBe($tec002->id);
});

it('creates an exam_resit_fee charge from a paid PTL DNG request without a finance link', function () {
    $dng = paidPtlDngRequest(['description' => 'Exam Retake Fee']);

    $result = app(CreateLegacyExamResitChargeFromPaidPtlAction::class)->run();

    expect($result['checked'])->toBe(1)
        ->and($result['created'])->toBe(1);

    $dng->refresh();
    $charge = FinanceCharge::query()->findOrFail($dng->finance_charge_id);

    expect($charge->charge_type)->toBe(FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->and($charge->description)->toBe('Exam Retake Fee: TEC002')
        ->and((float) $charge->amount)->toBe(3_000_000.0);
});

it('reconciles a charge linked to paid PTL as paid even when FinanceCharge paid_amount is zero', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $dng = paidPtlDngRequest();

    app(CreateLegacyExamResitChargeFromPaidPtlAction::class)->run();

    $charge = FinanceCharge::query()->findOrFail($dng->fresh()->finance_charge_id);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull();
});

it('creates charge and reconciles end-to-end through the artisan command for paid PTL without charge', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    paidPtlDngRequest();

    $this->artisan('academic:reconcile-legacy-exam-resit-fees')
        ->assertExitCode(0);

    $charge = FinanceCharge::query()
        ->where('student_id', $this->student->id)
        ->where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->sole();

    expect(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeTrue();
});
