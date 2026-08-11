<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\FinanceSetting;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `finance_settings.credit_offset_enabled` + `credit_offset_min_balance`
 * opt-in override the default no-waterfall-spill rule: when on and the
 * student's unapplied cash meets the threshold, CreateFinanceChargeAction
 * immediately offsets the new charge with it.
 */
function setCreditOffsetConfig(bool $enabled, float $minBalance): void
{
    FinanceSetting::query()->delete();
    FinanceSetting::create([
        'credit_offset_enabled' => $enabled,
        'credit_offset_min_balance' => $minBalance,
    ]);
}

function creditOffsetStudent(): Student
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    Payment::create([
        'student_id' => $student->id,
        'amount' => 10_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    return $student;
}

function createManualCharge(Student $student, float $amount): FinanceCharge
{
    static $counter = 0;
    $counter++;

    $billingAccount = app(BillingAccountProvisioner::class)->forStudent($student->id);

    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'credit_offset_test',
        'source_ref' => "credit-offset-test:{$student->id}:{$counter}",
        'obligation_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['test' => true],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $student->intake_semester_id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => $amount,
        'description' => 'Credit offset test charge',
        'effective_at' => now(),
    ]);
}

it('does not offset the charge when credit offset is disabled', function () {
    setCreditOffsetConfig(enabled: false, minBalance: 0);
    $student = creditOffsetStudent();

    createManualCharge($student, 3_000_000);

    $payment = Payment::where('student_id', $student->id)->firstOrFail();
    expect((float) $payment->unapplied_amount)->toBe(10_000_000.0);
});

it('offsets the charge with unapplied cash when enabled and balance meets the threshold', function () {
    setCreditOffsetConfig(enabled: true, minBalance: 5_000_000);
    $student = creditOffsetStudent();

    createManualCharge($student, 3_000_000);

    $payment = Payment::where('student_id', $student->id)->firstOrFail();
    expect((float) $payment->unapplied_amount)->toBe(7_000_000.0);
});

it('does not offset when enabled but balance is below the threshold', function () {
    setCreditOffsetConfig(enabled: true, minBalance: 20_000_000);
    $student = creditOffsetStudent();

    createManualCharge($student, 3_000_000);

    $payment = Payment::where('student_id', $student->id)->firstOrFail();
    expect((float) $payment->unapplied_amount)->toBe(10_000_000.0);
});

it('offsets only the charge just created, leaving other outstanding charges untouched', function () {
    setCreditOffsetConfig(enabled: false, minBalance: 0);
    $student = creditOffsetStudent();
    $preExistingCharge = createManualCharge($student, 4_000_000);

    setCreditOffsetConfig(enabled: true, minBalance: 1_000_000);
    $newCharge = createManualCharge($student, 3_000_000);

    $payment = Payment::where('student_id', $student->id)->firstOrFail();
    $settlement = app(SettlementService::class);

    // 10M unapplied, only the 3M new charge is offset — the pre-existing 4M
    // charge (higher priority under the old waterfall) is left alone.
    expect((float) $payment->unapplied_amount)->toBe(7_000_000.0)
        ->and($settlement->getChargePaidAmount($preExistingCharge->id))->toBe(0.0)
        ->and($settlement->getChargePaidAmount($newCharge->id))->toBe(3_000_000.0);
});
