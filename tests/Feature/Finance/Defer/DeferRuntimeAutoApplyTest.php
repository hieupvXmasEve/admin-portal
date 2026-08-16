<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\DeferCase;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-REV-020-03 (M3) — runtime auto-apply of the defer finance policy.
 *
 * Recording a FULL-scope PRESERVE/FORFEIT academic defer (non-EGC) settles the
 * student's obligations automatically, inside the same transaction as case
 * creation, by reusing the M1 ApplyDeferFinancePolicyAction. Live unpaid DNG is
 * closed locally before void; discount/non-FULL cases remain review-only.
 * Re-recording is idempotent. The backfill --apply-money flag settles
 * historical auto-safe cases with the same action.
 *
 * Helpers are local + uniquely named so the file is self-contained when run in
 * isolation and never collides with the other Defer test files in a full run.
 */
beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->returnSemester = Semester::factory()->create(['is_active' => false]);
    $this->user = User::factory()->create();
});

/** A course-stage student eligible to record a defer (non-EGC → auto-apply runs). */
function autoApplyCourseStudent(Semester $semester, Campus $campus, Program $program, string $code): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'status' => 'intake_course',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);
}

/** A legacy full-scope defer case created directly (student already 'deferred'). */
function autoApplyLegacyCase(Semester $semester, Campus $campus, Program $program, User $user, string $code, string $feePolicy): array
{
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'status' => 'deferred',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Legacy defer',
        'from_semester_id' => $semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $deferCase = DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => $feePolicy,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    return [$student, $deferCase];
}

function autoApplyRegistration(int $studentId, int $semesterId, string $status): CourseRegistration
{
    $offering = CourseOffering::factory()->create(['semester_id' => $semesterId]);

    return CourseRegistration::create([
        'student_id' => $studentId,
        'course_offering_id' => $offering->id,
        'semester_id' => $semesterId,
        'registration_status' => $status,
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);
}

function autoApplyObligation(int $studentId, int $semesterId, float $amount = 45_000_000): FinanceCharge
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance_test',
        'source_kind' => 'defer_runtime',
        'source_ref' => 'defer-runtime:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'defer-runtime:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'defer-runtime:test'],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $studentId,
        'semester_id' => $semesterId,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition obligation',
    ]);
}

function autoApplyPay(Student $student, FinanceCharge $charge, float $amount, User $user): Payment
{
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $line = InvoiceLine::where('charge_id', $charge->id)->firstOrFail();
    app(SettlementService::class)->createPaymentApplication($payment, $line, $amount, 'application', $user->id);

    return $payment;
}

function autoApplyInvariantOffending(): int
{
    return (int) collect(app(FinanceIntegrityAuditor::class)->summarize(null))
        ->sum(fn (array $row): int => (int) ($row['count'] ?? 0));
}

/** Record a FULL-scope academic defer through the real runtime action. */
function recordRuntimeDefer(Student $student, Semester $from, Semester $return, User $user, string $feePolicy): void
{
    RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Runtime defer auto-apply',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $from->id,
        'return_semester_id' => $return->id,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => $feePolicy,
    ]);
}

it('settles a runtime FULL PRESERVE defer: voids the obligation, preserves paid cash, records case + items', function () {
    $student = autoApplyCourseStudent($this->semester, $this->campus, $this->program, 'RT-PRES');
    $reg = autoApplyRegistration($student->id, $this->semester->id, 'registered');
    $charge = autoApplyObligation($student->id, $this->semester->id, 45_000_000);
    $payment = autoApplyPay($student, $charge, 45_000_000, $this->user);

    recordRuntimeDefer($student, $this->semester, $this->returnSemester, $this->user, DeferCase::POLICY_PRESERVE);

    $deferCase = DeferCase::where('student_id', $student->id)->firstOrFail();

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($payment->fresh()->unapplied_amount)->toBe(45_000_000.0)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0)
        ->and($deferCase->items()->count())->toBe(1)
        ->and($reg->fresh()->registration_status)->toBe('defer')
        ->and(autoApplyInvariantOffending())->toBe(0);
});

it('settles a runtime FULL FORFEIT defer: voids the obligation, creates an adjustment equal to paid, no residual debt', function () {
    $student = autoApplyCourseStudent($this->semester, $this->campus, $this->program, 'RT-FORF');
    autoApplyRegistration($student->id, $this->semester->id, 'registered');
    $charge = autoApplyObligation($student->id, $this->semester->id, 45_000_000);
    $payment = autoApplyPay($student, $charge, 45_000_000, $this->user);

    recordRuntimeDefer($student, $this->semester, $this->returnSemester, $this->user, DeferCase::POLICY_FORFEIT);

    $deferCase = DeferCase::where('student_id', $student->id)->firstOrFail();
    $adjustment = FinanceCharge::query()
        ->where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->whereHas('financeObligation', fn ($q) => $q
            ->where('source_kind', 'defer_forfeit')
            ->where('source_ref', 'defer_forfeit:'.$deferCase->id))
        ->first();

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($adjustment)->not->toBeNull()
        ->and((float) $adjustment->amount)->toBe(45_000_000.0)
        ->and(app(SettlementService::class)->getChargePaidAmount($adjustment->id))->toBe(45_000_000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0)
        ->and(autoApplyInvariantOffending())->toBe(0);
});

it('locally cancels live DNG and settles the linked runtime defer', function () {
    $student = autoApplyCourseStudent($this->semester, $this->campus, $this->program, 'RT-DNG');
    autoApplyRegistration($student->id, $this->semester->id, 'registered');
    $charge = autoApplyObligation($student->id, $this->semester->id, 45_000_000);

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'RT-DNG',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 45_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 45_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dng->id,
    ]);

    recordRuntimeDefer($student, $this->semester, $this->returnSemester, $this->user, DeferCase::POLICY_FORFEIT);

    $deferCase = DeferCase::where('student_id', $student->id)->firstOrFail();

    expect($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0)
        ->and($deferCase->items()->count())->toBe(1)
        ->and(autoApplyInvariantOffending())->toBe(0);
});

it('is idempotent: a second defer for the same student/semester does not double-settle', function () {
    $student = autoApplyCourseStudent($this->semester, $this->campus, $this->program, 'RT-IDEM');
    autoApplyRegistration($student->id, $this->semester->id, 'registered');
    $charge = autoApplyObligation($student->id, $this->semester->id, 45_000_000);
    autoApplyPay($student, $charge, 45_000_000, $this->user);

    recordRuntimeDefer($student, $this->semester, $this->returnSemester, $this->user, DeferCase::POLICY_FORFEIT);
    // A second defer (allowed from 'deferred') must not re-settle the already-voided obligation.
    recordRuntimeDefer($student, $this->semester, $this->returnSemester, $this->user, DeferCase::POLICY_FORFEIT);

    expect(FinanceCharge::where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->where('status', FinanceCharge::STATUS_ACTIVE)->count())->toBe(1)
        ->and(FinanceCharge::where('student_id', $student->id)->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->where('status', FinanceCharge::STATUS_VOID)->count())->toBe(1)
        ->and(autoApplyInvariantOffending())->toBe(0);
});
