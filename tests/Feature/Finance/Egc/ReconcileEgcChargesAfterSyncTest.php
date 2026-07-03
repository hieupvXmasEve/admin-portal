<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\DiscountAllocation;
use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Finance\Actions\Egc\ReconcileEgcChargesAfterSyncAction;
use App\Modules\Finance\Actions\Egc\SyncEgcBlockResultsAction;
use App\Modules\Finance\Queries\Egc\ListEgcRetakeAdjustmentsQuery;
use App\Modules\Finance\Services\SettlementService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function makeReconcileStudent(array $state = []): Student
{
    $intakeSemester = Semester::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->state(['semester_id' => $intakeSemester->id])
        ->create();

    return Student::factory()->state(array_merge([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_pre_uni_gc',
        'gc_total_levels' => 6,
    ], $state))->create();
}

function makeReconcileEgcUnit(int $level, int $baseFee): Unit
{
    return Unit::factory()->state([
        'unit_type' => 'egc',
        'level' => $level,
        'base_fee' => $baseFee,
    ])->create();
}

function makeReconcileCourseOffering(Semester $semester, Unit $unit): CourseOffering
{
    $attributes = CourseOffering::factory()->state([
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
    ])->raw();

    unset($attributes['drop_deadline'], $attributes['withdrawal_deadline']);

    return CourseOffering::query()->create($attributes);
}

function makeReconcileChargeWithInvoice(Student $student, Semester $semester, int $level, int $amount): array
{
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => $amount,
        'description' => "EGC Level {$level} Fee",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'EGC-REC-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);

    return [$charge, $invoice, $line];
}

function makeReconcileBlock(Student $student, Semester $semester, int $blockNumber, int $level, string $result, ?float $attendanceRate = null): EgcBlock
{
    return EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => $blockNumber,
        'level_number' => $level,
        'result' => $result,
        'attendance_rate' => $attendanceRate,
    ])->create();
}

function attachReconcileCharge(EgcBlock $block, FinanceCharge $charge): EgcBlock
{
    $block->update(['finance_charge_id' => $charge->id]);

    return $block->fresh();
}

function payReconcileLine(Student $student, InvoiceLine $line, int $amount): void
{
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_CASH,
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    app(SettlementService::class)->createPaymentApplication($payment, $line, $amount, 'application');
}

function grantEgcReconcilePermissions(array $codes): User
{
    $user = User::factory()->create();
    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($codes);
    app()->instance(PermissionService::class, $mock);

    return $user;
}

it('relevels only the immediate next-semester block and auto-applies retake discount after a block two failure', function () {
    $sourceSemester = Semester::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_archived' => false]);
    $targetSemester = Semester::factory()->create(['start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'is_archived' => false]);
    $student = makeReconcileStudent();

    makeReconcileEgcUnit(3, 15_000_000);
    makeReconcileEgcUnit(4, 17_000_000);
    makeReconcileEgcUnit(5, 19_000_000);

    [$sourceCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 3, 15_000_000);
    $sourceBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 2, 3, EgcBlock::RESULT_FAIL, 97.14),
        $sourceCharge,
    );

    [$firstTargetCharge, , $firstTargetLine] = makeReconcileChargeWithInvoice($student, $targetSemester, 4, 17_000_000);
    $firstTargetBlock = attachReconcileCharge(
        makeReconcileBlock($student, $targetSemester, 1, 4, EgcBlock::RESULT_PENDING),
        $firstTargetCharge,
    );

    [$secondTargetCharge, , $secondTargetLine] = makeReconcileChargeWithInvoice($student, $targetSemester, 5, 19_000_000);
    $secondTargetBlock = attachReconcileCharge(
        makeReconcileBlock($student, $targetSemester, 2, 5, EgcBlock::RESULT_PENDING),
        $secondTargetCharge,
    );

    payReconcileLine($student, $firstTargetLine, 17_000_000);

    $summary = ReconcileEgcChargesAfterSyncAction::run($sourceSemester->id);

    expect($summary['students_reconciled'])->toBe(1)
        ->and($summary['releveled_blocks'])->toBe(1)
        ->and($summary['discounts_applied'])->toBe(1)
        ->and($summary['needs_manual_repair'])->toBe([]);

    expect($firstTargetBlock->fresh()->level_number)->toBe(3)
        ->and($firstTargetBlock->fresh()->is_retake)->toBeTrue()
        ->and($secondTargetBlock->fresh()->level_number)->toBe(5)
        ->and($secondTargetBlock->fresh()->is_retake)->toBeFalse();

    expect((float) $firstTargetCharge->fresh()->amount)->toBe(15_000_000.0)
        ->and($firstTargetCharge->fresh()->description)->toBe('EGC Level 3 Fee')
        ->and((float) $secondTargetCharge->fresh()->amount)->toBe(19_000_000.0)
        ->and($secondTargetCharge->fresh()->description)->toBe('EGC Level 5 Fee');

    expect((float) $firstTargetLine->fresh()->amount_snapshot)->toBe(15_000_000.0)
        ->and($firstTargetLine->fresh()->description_snapshot)->toBe('EGC Level 3 Fee')
        ->and((float) $secondTargetLine->fresh()->amount_snapshot)->toBe(19_000_000.0)
        ->and($secondTargetLine->fresh()->description_snapshot)->toBe('EGC Level 5 Fee');

    expect($sourceBlock->fresh()->retake_discount_id)->not->toBeNull()
        ->and(EgcRetakeDiscountLink::query()->where('target_finance_charge_id', $firstTargetCharge->id)->exists())->toBeTrue()
        ->and(DiscountAllocation::query()->sum('amount'))->toBe('7500000.00')
        ->and((float) PaymentApplication::query()->where('invoice_line_id', $firstTargetLine->id)->sum('amount'))->toBe(7_500_000.0);

    $adjustments = app(ListEgcRetakeAdjustmentsQuery::class)->handle($sourceSemester->id);

    expect(collect($adjustments['eligible_no_targets'])->pluck('id'))->not->toContain($sourceBlock->id)
        ->and(collect($adjustments['already_discounted'])->pluck('id'))->toContain($sourceBlock->id);
});

it('runs reconciliation automatically from block result sync', function () {
    $sourceSemester = Semester::factory()->create();
    $student = makeReconcileStudent();

    $levelThree = makeReconcileEgcUnit(3, 15_000_000);

    [$sourceCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 3, 15_000_000);
    $sourceBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 1, 3, EgcBlock::RESULT_PENDING),
        $sourceCharge,
    );

    [$targetCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 3, 15_000_000);
    $targetBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 2, 3, EgcBlock::RESULT_PENDING),
        $targetCharge,
    );

    $sourceOffering = makeReconcileCourseOffering($sourceSemester, $levelThree);
    $targetOffering = makeReconcileCourseOffering($sourceSemester, $levelThree);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $sourceOffering->id,
        'semester_id' => $sourceSemester->id,
        'registration_status' => 'completed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $targetOffering->id,
        'semester_id' => $sourceSemester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now()->addMinute(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
    ]);

    AcademicRecord::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $sourceSemester->id,
        'unit_id' => $levelThree->id,
        'course_offering_id' => $sourceOffering->id,
        'completion_status' => 'completed',
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 97.14,
    ])->create();

    AcademicRecord::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $sourceSemester->id,
        'unit_id' => $levelThree->id,
        'course_offering_id' => $targetOffering->id,
        'completion_status' => 'in_progress',
        'is_passed' => null,
        'override_pass' => false,
    ])->create();

    $summary = SyncEgcBlockResultsAction::run($sourceSemester->id);

    expect($summary['synced'])->toBe(2)
        ->and($summary['reconciliation']['students_reconciled'])->toBe(1)
        ->and($summary['reconciliation']['releveled_blocks'])->toBe(1)
        ->and($summary['reconciliation']['discounts_applied'])->toBe(1)
        ->and($sourceBlock->fresh()->result)->toBe(EgcBlock::RESULT_FAIL)
        ->and((float) $sourceBlock->fresh()->attendance_rate)->toBe(97.14)
        ->and($sourceBlock->fresh()->retake_discount_id)->not->toBeNull()
        ->and($targetBlock->fresh()->level_number)->toBe(3)
        ->and($targetBlock->fresh()->is_retake)->toBeTrue();
});

it('is idempotent after an automatic discount is applied', function () {
    $sourceSemester = Semester::factory()->create();
    $student = makeReconcileStudent();

    makeReconcileEgcUnit(3, 15_000_000);

    [$sourceCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 3, 15_000_000);
    $sourceBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 1, 3, EgcBlock::RESULT_FAIL, 92.0),
        $sourceCharge,
    );

    [$targetCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 4, 15_000_000);
    attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 2, 4, EgcBlock::RESULT_PENDING),
        $targetCharge,
    );

    ReconcileEgcChargesAfterSyncAction::run($sourceSemester->id);
    $secondRun = ReconcileEgcChargesAfterSyncAction::run($sourceSemester->id);

    expect($secondRun['students_reconciled'])->toBe(0)
        ->and($secondRun['releveled_blocks'])->toBe(0)
        ->and($secondRun['discounts_applied'])->toBe(0)
        ->and(EgcRetakeDiscountLink::query()->count())->toBe(1)
        ->and($sourceBlock->fresh()->retake_discount_id)->not->toBeNull();
});

it('relevels below-attendance failures without applying retake discount', function () {
    $sourceSemester = Semester::factory()->create();
    $student = makeReconcileStudent();

    makeReconcileEgcUnit(4, 15_000_000);

    [$sourceCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 4, 15_000_000);
    $sourceBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 1, 4, EgcBlock::RESULT_FAIL, 70.0),
        $sourceCharge,
    );

    [$targetCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 5, 15_000_000);
    $targetBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 2, 5, EgcBlock::RESULT_PENDING),
        $targetCharge,
    );

    $summary = ReconcileEgcChargesAfterSyncAction::run($sourceSemester->id);

    expect($summary['students_reconciled'])->toBe(1)
        ->and($summary['releveled_blocks'])->toBe(1)
        ->and($summary['discounts_applied'])->toBe(0)
        ->and($targetBlock->fresh()->level_number)->toBe(4)
        ->and($targetBlock->fresh()->is_retake)->toBeTrue()
        ->and($sourceBlock->fresh()->retake_discount_id)->toBeNull()
        ->and(EgcRetakeDiscountLink::query()->count())->toBe(0);
});

it('does not use next-semester block one as the retake target for a block one failure', function () {
    $sourceSemester = Semester::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_archived' => false]);
    $targetSemester = Semester::factory()->create(['start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'is_archived' => false]);
    $student = makeReconcileStudent();

    makeReconcileEgcUnit(3, 15_000_000);
    makeReconcileEgcUnit(4, 17_000_000);

    [$sourceCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 3, 15_000_000);
    $sourceBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 1, 3, EgcBlock::RESULT_FAIL, 97.14),
        $sourceCharge,
    );

    [$targetCharge] = makeReconcileChargeWithInvoice($student, $targetSemester, 4, 17_000_000);
    $targetBlock = attachReconcileCharge(
        makeReconcileBlock($student, $targetSemester, 1, 4, EgcBlock::RESULT_PENDING),
        $targetCharge,
    );

    $summary = ReconcileEgcChargesAfterSyncAction::run($sourceSemester->id);

    expect($summary['students_reconciled'])->toBe(0)
        ->and($summary['releveled_blocks'])->toBe(0)
        ->and($summary['discounts_applied'])->toBe(0)
        ->and($summary['skipped'][0]['reason'])->toBe('no_pending_target_blocks')
        ->and($sourceBlock->fresh()->retake_discount_id)->toBeNull()
        ->and($targetBlock->fresh()->level_number)->toBe(4)
        ->and($targetBlock->fresh()->is_retake)->toBeFalse();
});

it('does not relevel or discount when the student is no longer in egc stage', function () {
    $sourceSemester = Semester::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_archived' => false]);
    $targetSemester = Semester::factory()->create(['start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'is_archived' => false]);
    $student = makeReconcileStudent(['status' => 'intake_course']);

    makeReconcileEgcUnit(4, 15_000_000);
    makeReconcileEgcUnit(5, 15_000_000);

    [$sourceCharge] = makeReconcileChargeWithInvoice($student, $sourceSemester, 4, 15_000_000);
    $sourceBlock = attachReconcileCharge(
        makeReconcileBlock($student, $sourceSemester, 2, 4, EgcBlock::RESULT_FAIL, 97.14),
        $sourceCharge,
    );

    [$targetCharge] = makeReconcileChargeWithInvoice($student, $targetSemester, 5, 15_000_000);
    $targetBlock = attachReconcileCharge(
        makeReconcileBlock($student, $targetSemester, 1, 5, EgcBlock::RESULT_PENDING),
        $targetCharge,
    );

    $summary = ReconcileEgcChargesAfterSyncAction::run($sourceSemester->id);

    expect($summary['students_reconciled'])->toBe(0)
        ->and($summary['needs_manual_repair'][0]['reason'])->toBe('student_not_in_egc_stage')
        ->and($sourceBlock->fresh()->retake_discount_id)->toBeNull()
        ->and($targetBlock->fresh()->level_number)->toBe(5)
        ->and($targetBlock->fresh()->is_retake)->toBeFalse()
        ->and(EgcRetakeDiscountLink::query()->count())->toBe(0);
});

it('redirects the old retake adjustments index to the combined block results surface', function () {
    $semester = Semester::factory()->create();
    $campus = Campus::factory()->create();
    $user = grantEgcReconcilePermissions(['view_egc_retake_adjustments']);
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    actingAs($user)
        ->get(route('finance.egc.retake-adjustments.index', ['semester_id' => $semester->id]))
        ->assertRedirect(route('finance.egc.block-results.index', [
            'semester_id' => $semester->id,
            'section' => 'retake-adjustments',
        ]));
});

it('rejects block result sync for a non-active semester', function () {
    $campus = Campus::factory()->create();
    $activeSemester = Semester::factory()->active()->create();
    $inactiveSemester = Semester::factory()->create(['is_active' => false]);
    $student = makeReconcileStudent();
    $levelThree = makeReconcileEgcUnit(3, 15_000_000);
    $user = grantEgcReconcilePermissions(['sync_egc_block_results']);

    $sourceBlock = makeReconcileBlock($student, $inactiveSemester, 1, 3, EgcBlock::RESULT_PENDING);
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    AcademicRecord::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $inactiveSemester->id,
        'unit_id' => $levelThree->id,
        'course_offering_id' => makeReconcileCourseOffering($inactiveSemester, $levelThree)->id,
        'completion_status' => 'completed',
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 97.14,
    ])->create();

    actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->from(route('finance.egc.block-results.index'))
        ->post(route('finance.egc.block-results.sync'), [
            '_token' => 'test-csrf-token',
            'semester_id' => $inactiveSemester->id,
        ])
        ->assertRedirect(route('finance.egc.block-results.index'))
        ->assertSessionHasErrors('semester_id');

    expect($activeSemester->is_active)->toBeTrue()
        ->and($sourceBlock->fresh()->result)->toBe(EgcBlock::RESULT_PENDING)
        ->and($sourceBlock->fresh()->synced_at)->toBeNull();
});
