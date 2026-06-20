<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentInvoice;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\Operations\ApplyDeferFinancePolicyAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-REV-020-04 (M4) — Re-enrollment charge + preserved-cash allocation.
 *
 * Closing increment of the defer money slice. After a FULL-scope PRESERVE defer
 * (settled by M1 ApplyDeferFinancePolicyAction) the original obligation is voided
 * and the paid cash sits unapplied/available. This story proves the re-enrollment
 * end of the contract using only existing ledger operations — no new engine:
 *
 *  - The defer term (S1) stays non-billable (M2 guard), but a later RETURN term
 *    (S2) where the student re-enrolls with a fresh non-defer registration bills
 *    a normal tuition charge through GenerateBatchChargesAction. Preserve no
 *    longer "skips the future charge".
 *  - The student's preserved available cash is auto-allocated onto that new
 *    charge through the normal allocation path (AutoAllocatePaymentsAction).
 *  - Re-enrollment lineage rides course_registrations.original_registration_id.
 *  - finance:audit-invariants stays clean: one invoice per (student, semester),
 *    no synthetic credit/debt, the preserved cash — not new money — settles it.
 *
 * Modelling notes (kept faithful + invariant-safe):
 *  - Two real terms exist: the defer term S1 (past) and the return term S2
 *    (active). getTuitionTermData() counts semesters from intake_major to the
 *    target inclusive, so with exactly these two S1 => term 1, S2 => term 2.
 *  - Re-enrollment is cross-semester (the real flow: defer this term, return a
 *    future term). Same-semester regeneration is intentionally avoided: the
 *    PRESERVE void cancels S1's invoice, and a second invoice in S1 would trip
 *    INV-6 (duplicate invoice per student/semester).
 *  - The settled student stays 'intake_course' (a resumed/active student) so
 *    batch generation includes them for S2; M1's settlement never touches
 *    student.status.
 *  - Helpers are local + uniquely prefixed so the file is self-contained in
 *    isolation and never collides with the sibling Defer test files.
 */
beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->user = User::factory()->create();

    $this->deferSemester = Semester::factory()->create([
        'code' => 'M4-FALL2025',
        'name' => 'M4 Fall 2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => false,
        'is_archived' => false,
    ]);

    $this->returnSemester = Semester::factory()->create([
        'code' => 'M4-SPRING2026',
        'name' => 'M4 Spring 2026',
        'start_date' => '2026-01-05 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);

    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->deferSemester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->instance('campus', $this->campus);
});

/** A course-stage student with a 2-term tuition plan (term 1 = S1, term 2 = S2). */
function reEnrollStudent(Campus $campus, Program $program, CurriculumVersion $cv, Semester $intake, string $code): Student
{
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'full_name' => 'Re-Enroll '.$code,
            'status' => 'intake_course',
            'curriculum_version_id' => $cv->id,
            'intake_semester_id' => $intake->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_course' => (string) $intake->id,
            'intake_major' => $intake->id,
        ]);

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intake->id,
        'total_amount' => 90_000_000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45_000_000,
        'due_date' => '2025-12-13',
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 2,
        'amount' => 45_000_000,
        'due_date' => '2026-04-13',
    ]);

    return $student;
}

/** A single course registration in a semester (optionally linked to its original). */
function reEnrollRegistration(int $studentId, int $semesterId, string $status, ?int $originalId = null): CourseRegistration
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
        'original_registration_id' => $originalId,
    ]);
}

/** Pay an obligation in full so the PRESERVE void has real cash to release. */
function reEnrollPay(Student $student, FinanceCharge $charge, float $amount, User $user): Payment
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

/** Total offending rows across every finance invariant (0 = clean). */
function reEnrollInvariantOffending(): int
{
    return (int) collect(app(FinanceIntegrityAuditor::class)->summarize(null))
        ->sum(fn (array $row): int => (int) ($row['count'] ?? 0));
}

/**
 * Arrange a student who fully paid the defer-term obligation, then recorded a
 * FULL-scope PRESERVE defer settled by M1: the obligation is voided and the paid
 * cash is preserved/unapplied, the defer-term registration is marked 'defer'.
 *
 * @return array{0: Student, 1: CourseRegistration, 2: Payment, 3: FinanceCharge}
 */
function reEnrollArrangePreserved(Campus $campus, Program $program, CurriculumVersion $cv, Semester $deferSemester, User $user, string $code): array
{
    $student = reEnrollStudent($campus, $program, $cv, $deferSemester, $code);
    $originalReg = reEnrollRegistration($student->id, $deferSemester->id, 'registered');

    $originalCharge = app(CreateFinanceChargeAction::class)->handle([
        'student_id' => $student->id,
        'semester_id' => $deferSemester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45_000_000,
        'description' => 'Major Tuition (Installment 1)',
    ]);

    $payment = reEnrollPay($student, $originalCharge, 45_000_000, $user);

    // The academic FULL defer marks every active registration non-billable.
    $originalReg->update(['registration_status' => 'defer']);

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'M4 preserve defer',
        'from_semester_id' => $deferSemester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $case = DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $deferSemester->id,
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    app(ApplyDeferFinancePolicyAction::class)->handle($case, $user->id);

    return [$student, $originalReg, $payment, $originalCharge->fresh()];
}

/** Generate tuition charges for a single uploaded student in a semester. */
function reEnrollGenerateTuition(Student $student, Semester $semester): array
{
    return GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);
}

it('precondition: the PRESERVE defer voids the obligation and preserves the paid cash', function () {
    [$student, , $payment, $originalCharge] = reEnrollArrangePreserved(
        $this->campus, $this->program, $this->curriculumVersion, $this->deferSemester, $this->user, 'M4-PRE'
    );

    expect($originalCharge->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($payment->fresh()->unapplied_amount)->toBe(45_000_000.0)
        ->and(app(DeferChargeResolver::class)->isSemesterEnrollmentDeferred($student->fresh(), $this->deferSemester->id))->toBeTrue()
        ->and(app(DeferChargeResolver::class)->isSemesterEnrollmentDeferred($student->fresh(), $this->returnSemester->id))->toBeFalse()
        ->and(reEnrollInvariantOffending())->toBe(0);
});

it('generates a normal tuition charge for the re-enrollment term (preserve no longer skips it) with lineage', function () {
    [$student, $originalReg] = reEnrollArrangePreserved(
        $this->campus, $this->program, $this->curriculumVersion, $this->deferSemester, $this->user, 'M4-GEN'
    );

    // The student re-enrolls in the return term: a fresh non-defer registration
    // linked to the original via original_registration_id.
    $reEnrollReg = reEnrollRegistration($student->id, $this->returnSemester->id, 'registered', $originalReg->id);

    $result = reEnrollGenerateTuition($student, $this->returnSemester);

    $newCharge = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $this->returnSemester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->first();

    expect($result['created_count'])->toBe(1)
        ->and($newCharge)->not->toBeNull()
        ->and((float) $newCharge->amount)->toBe(45_000_000.0)
        ->and($newCharge->id)->not->toBe(/* original voided charge */ FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $this->deferSemester->id)
        ->where('status', FinanceCharge::STATUS_VOID)
        ->value('id'))
        ->and($reEnrollReg->fresh()->original_registration_id)->toBe($originalReg->id)
        ->and(reEnrollInvariantOffending())->toBe(0);
});

it('auto-allocates the preserved available cash onto the re-enrollment charge via the normal allocation path', function () {
    [$student, $originalReg, $payment] = reEnrollArrangePreserved(
        $this->campus, $this->program, $this->curriculumVersion, $this->deferSemester, $this->user, 'M4-ALLOC'
    );

    reEnrollRegistration($student->id, $this->returnSemester->id, 'registered', $originalReg->id);
    reEnrollGenerateTuition($student, $this->returnSemester);

    $newCharge = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $this->returnSemester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->firstOrFail();

    // Preserved cash is unapplied before allocation, fully applied after.
    expect($payment->fresh()->unapplied_amount)->toBe(45_000_000.0);

    $stats = app(AutoAllocatePaymentsAction::class)->runForStudents(
        [$student->id],
        AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
        $this->user->id,
    );

    expect($stats['allocations_created'])->toBeGreaterThan(0)
        ->and((float) $stats['total_allocated_amount'])->toBe(45_000_000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0)
        ->and(app(SettlementService::class)->getChargePaidAmount($newCharge->id))->toBe(45_000_000.0)
        ->and(reEnrollInvariantOffending())->toBe(0);
});

it('settles the re-enrollment with preserved cash only — no new money, no synthetic credit/debt, invariants clean', function () {
    [$student, $originalReg, $payment] = reEnrollArrangePreserved(
        $this->campus, $this->program, $this->curriculumVersion, $this->deferSemester, $this->user, 'M4-CYCLE'
    );

    reEnrollRegistration($student->id, $this->returnSemester->id, 'registered', $originalReg->id);
    reEnrollGenerateTuition($student, $this->returnSemester);
    app(AutoAllocatePaymentsAction::class)->runForStudents(
        [$student->id],
        AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
        $this->user->id,
    );

    // Exactly one payment ever existed (the original) — the preserved cash funds
    // the re-enrollment, no new money was invented.
    expect(Payment::where('student_id', $student->id)->count())->toBe(1)
        ->and((float) Payment::where('student_id', $student->id)->sum('amount'))->toBe(45_000_000.0)
        // No defer adjustment charge (that is FORFEIT, not PRESERVE).
        ->and(FinanceCharge::where('student_id', $student->id)->where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)->count())->toBe(0)
        // One invoice per semester (defer term cancelled, return term active) — INV-6 safe.
        ->and(StudentInvoice::where('student_id', $student->id)->where('semester_id', $this->deferSemester->id)->count())->toBe(1)
        ->and(StudentInvoice::where('student_id', $student->id)->where('semester_id', $this->returnSemester->id)->count())->toBe(1)
        ->and(reEnrollInvariantOffending())->toBe(0);
});
