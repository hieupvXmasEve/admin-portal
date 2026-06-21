<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\DeferCaseItem;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\BackfillDeferCaseItemsAction;
use App\Modules\Finance\Queries\Operations\ClassifyDeferBackfillCandidatesQuery;
use App\Modules\Finance\Support\DeferBackfillClassification as C;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-REV-020 — Phase 2: read-only backfill dry-run + item-level backfill.
 *
 * Legacy full-scope defer cases were created before runtime itemization, so
 * they carry no defer_case_items. The backfill classifies each case (auto-safe
 * vs needs-review) and, only when applied, creates the missing items and marks
 * the registrations non-billable. No money is mutated in this phase.
 */
beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
});

function legacyFullDeferCase(
    Semester $semester,
    Campus $campus,
    Program $program,
    string $code,
    string $feePolicy = DeferCase::POLICY_FORFEIT,
    ?float $preserveAmount = null,
): array {
    $user = User::factory()->create();
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
        'reason' => 'Legacy full-scope defer',
        'from_semester_id' => $semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    // Legacy case: created directly (bypasses runtime itemization) => no items.
    $deferCase = DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => $feePolicy,
        'applies_once' => true,
        'preserve_amount' => $preserveAmount,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    return [$student, $deferCase];
}

function enrolledRegistration(int $studentId, int $semesterId, string $status = 'confirmed'): CourseRegistration
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

it('classifies a legacy full-scope case with active registrations as itemizable and writes nothing in dry-run', function () {
    [$student, $deferCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-ITEMIZABLE-01');
    $reg = enrolledRegistration($student->id, $this->semester->id, 'confirmed');

    $result = app(BackfillDeferCaseItemsAction::class)->handle(apply: false, semesterId: $this->semester->id);

    expect($result['mode'])->toBe('dry-run')
        ->and($result['counts']['itemizable'])->toBe(1)
        ->and($result['planned_item_count'])->toBe(1)
        ->and($result['items_created'])->toBe(0)
        // dry-run must not touch the database
        ->and(DeferCaseItem::count())->toBe(0)
        ->and($reg->fresh()->registration_status)->toBe('confirmed');
});

it('backfills missing items and marks registrations defer when applied, idempotently', function () {
    [$student, $deferCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-APPLY-01');
    $regOne = enrolledRegistration($student->id, $this->semester->id, 'confirmed');
    $regTwo = enrolledRegistration($student->id, $this->semester->id, 'registered');

    $first = app(BackfillDeferCaseItemsAction::class)->handle(apply: true, semesterId: $this->semester->id);

    expect($first['mode'])->toBe('apply')
        ->and($first['items_created'])->toBe(2)
        ->and($first['registrations_marked'])->toBe(2)
        ->and($deferCase->items()->count())->toBe(2)
        ->and($regOne->fresh()->registration_status)->toBe('defer')
        ->and($regTwo->fresh()->registration_status)->toBe('defer');

    // Idempotent: a second apply creates no duplicates.
    $second = app(BackfillDeferCaseItemsAction::class)->handle(apply: true, semesterId: $this->semester->id);

    expect($second['items_created'])->toBe(0)
        ->and($deferCase->items()->count())->toBe(2);
});

it('classifies a full-scope case with no active registrations as needs-review', function () {
    [$student, $deferCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-NOREG-01');
    // completed registration is not an active enrollment => nothing to itemize
    enrolledRegistration($student->id, $this->semester->id, 'completed');

    $result = app(ClassifyDeferBackfillCandidatesQuery::class)->handle($this->semester->id);
    $case = collect($result['cases'])->firstWhere('defer_case_id', $deferCase->id);

    expect($case['itemization'])->toBe(C::ITEMIZATION_NO_REGISTRATION)
        ->and($case['review_required'])->toBeTrue()
        ->and($result['counts']['no_registration'])->toBe(1)
        ->and($result['counts']['itemizable'])->toBe(0);
});

it('skips a case that is already itemized', function () {
    [$student, $deferCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-DONE-01');
    $reg = enrolledRegistration($student->id, $this->semester->id, 'defer');
    DeferCaseItem::create([
        'defer_case_id' => $deferCase->id,
        'course_registration_id' => $reg->id,
        'fee_policy' => DeferCase::POLICY_FORFEIT,
    ]);

    $result = app(ClassifyDeferBackfillCandidatesQuery::class)->handle($this->semester->id);
    $case = collect($result['cases'])->firstWhere('defer_case_id', $deferCase->id);

    expect($case['itemization'])->toBe(C::ITEMIZATION_ALREADY_ITEMIZED)
        ->and($result['counts']['already_itemized'])->toBe(1);
});

it('reports finance flags for the money phase', function () {
    // no charge
    [$noChargeStudent, $noChargeCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-NOCHG');
    enrolledRegistration($noChargeStudent->id, $this->semester->id);

    // active charge present
    [$activeStudent, $activeCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-ACTCHG');
    enrolledRegistration($activeStudent->id, $this->semester->id);
    FinanceCharge::create([
        'student_id' => $activeStudent->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    // voided charge only
    [$voidStudent, $voidCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-VOIDCHG');
    enrolledRegistration($voidStudent->id, $this->semester->id);
    FinanceCharge::create([
        'student_id' => $voidStudent->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45000000,
        'description' => 'Tuition (void)',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => now(),
        'void_reason' => 'legacy',
    ]);

    // partial policy with missing preserve amount
    [$partialStudent, $partialCase] = legacyFullDeferCase(
        $this->semester, $this->campus, $this->program, 'BF-PARTIAL', DeferCase::POLICY_PARTIAL, null
    );
    enrolledRegistration($partialStudent->id, $this->semester->id);

    $result = app(ClassifyDeferBackfillCandidatesQuery::class)->handle($this->semester->id);
    $byId = collect($result['cases'])->keyBy('defer_case_id');

    expect($byId[$noChargeCase->id]['finance_flags'])->toContain(C::FLAG_NO_CHARGE)
        ->and($byId[$activeCase->id]['finance_flags'])->toContain(C::FLAG_HAS_ACTIVE_CHARGE)
        ->and($byId[$voidCase->id]['finance_flags'])->toContain(C::FLAG_CHARGE_VOIDED)
        ->and($byId[$partialCase->id]['finance_flags'])->toContain(C::FLAG_AMBIGUOUS_PARTIAL)
        ->and($byId[$partialCase->id]['review_required'])->toBeTrue();
});

it('runs the finance:defer-backfill command read-only by default and applies with --apply', function () {
    [$student, $deferCase] = legacyFullDeferCase($this->semester, $this->campus, $this->program, 'BF-CMD-01');
    enrolledRegistration($student->id, $this->semester->id, 'confirmed');

    // default = dry-run, writes nothing
    $this->artisan('finance:defer-backfill', ['--semester' => $this->semester->id])
        ->assertExitCode(0);
    expect(DeferCaseItem::count())->toBe(0);

    // --apply writes items
    $this->artisan('finance:defer-backfill', ['--semester' => $this->semester->id, '--apply' => true])
        ->assertExitCode(0);
    expect($deferCase->items()->count())->toBe(1);
});
