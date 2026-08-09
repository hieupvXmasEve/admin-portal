<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Shared\Contracts\Identity\CampusPermissionReader;

const BATCH_STUDIO_CSRF = 'batch-studio-test-csrf';

/**
 * Grant a user a fixed set of finance permissions for the active campus and
 * bind the campus context the way HandleInertiaRequests + finance controllers expect.
 *
 * @param  string[]  $permissions
 */
function grantFinance(User $user, array $permissions, Campus $campus): void
{
    session(financeWebSession($campus));
    app()->singleton('campus', fn () => $campus);

    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
    app()->instance(CampusPermissionReader::class, $mock);
}

/**
 * @return array{current_campus_id: int, _token: string}
 */
function financeWebSession(Campus $campus): array
{
    return [
        'current_campus_id' => $campus->id,
        '_token' => BATCH_STUDIO_CSRF,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function financePostPayload(array $payload): array
{
    return array_merge(['_token' => BATCH_STUDIO_CSRF], $payload);
}

function makeBatchStartedStudent(Campus $campus, Semester $targetSemester, string $studentCode): Student
{
    $intakeSemester = Semester::factory()->create([
        'start_date' => $targetSemester->start_date->copy()->subMonths(4),
        'end_date' => $targetSemester->start_date->copy()->subMonth(),
    ]);

    return Student::factory()->forCampus($campus)->create([
        'student_id' => $studentCode,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $intakeSemester->id,
    ]);
}

function makeBatchHpStudent(Campus $campus, Semester $targetSemester, string $studentCode, string $status = 'intake_course'): Student
{
    $program = Program::factory()->create();
    $intakeSemester = Semester::factory()->create([
        'start_date' => $targetSemester->start_date->copy()->subMonths(4),
        'end_date' => $targetSemester->start_date->copy()->subMonth(),
    ]);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($intakeSemester)
        ->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $studentCode,
            'status' => $status,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $intakeSemester->id,
            'intake_course' => (string) $intakeSemester->id,
            'intake_major' => $targetSemester->id,
        ]);

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $intakeSemester->id,
        'total_amount' => 45000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => $targetSemester->start_date->copy()->addDays(14)->toDateString(),
    ]);

    return $student;
}

function makeBatchEgcStudent(Campus $campus, Semester $targetSemester, string $studentCode, array $state = []): Student
{
    $program = Program::factory()->create();
    $intakeSemester = Semester::factory()->create([
        'start_date' => $targetSemester->start_date->copy()->subMonths(4),
        'end_date' => $targetSemester->start_date->copy()->subMonth(),
    ]);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($intakeSemester)
        ->create();

    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create(array_merge([
            'student_id' => $studentCode,
            'status' => 'intake_pre_uni_gc',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $intakeSemester->id,
            'gc_current_level' => 1,
            'gc_total_levels' => 6,
        ], $state));
}

function seedBatchEgcBlockCharge(
    Student $student,
    Semester $semester,
    int $blockNumber,
    int $levelNumber,
    string $chargeStatus = FinanceCharge::STATUS_ACTIVE,
    string $lineStatus = 'active',
    string $invoiceStatus = 'draft',
): EgcBlock {
    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'batch_egc_block_charge_setup',
        'source_ref' => 'batch-egc-setup:'.$student->id.'-'.$semester->id.'-'.$blockNumber.'-'.random_int(1000, 9999),
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 15_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'batch-egc-test:1',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => "EGC Level {$levelNumber} Fee",
        'effective_at' => now(),
        'status' => $chargeStatus,
        'voided_at' => $chargeStatus === FinanceCharge::STATUS_VOID ? now() : null,
        'void_reason' => $chargeStatus === FinanceCharge::STATUS_VOID ? 'Batch EGC regression setup' : null,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'BATCH-EGC-'.$student->id.'-'.$semester->id.'-'.$blockNumber.'-'.random_int(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'status' => $invoiceStatus,
        'due_date' => now()->addDays(30),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
        'status' => $lineStatus,
        'voided_at' => $lineStatus === 'void' ? now() : null,
        'void_reason' => $lineStatus === 'void' ? 'Batch EGC regression setup' : null,
    ]);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => $blockNumber,
        'level_number' => $levelNumber,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    app(EgcBlockFinanceResolver::class)->bindExistingCharge($block, $charge);

    return $block;
}

function seedBatchActiveCharge(Student $student, Semester $semester, string $chargeType): FinanceCharge
{
    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $chargeType,
        'amount' => 100000,
        'description' => 'Existing charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}
