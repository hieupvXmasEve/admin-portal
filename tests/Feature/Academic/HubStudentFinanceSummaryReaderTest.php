<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\StudentWallet;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue 06 — Seam 2 (Hub reads): the read-only finance summary contract.
 *
 * The Hub is finance-aware but not finance-owning (ADR-0007): it reads fees,
 * gold, and scholarships through the Finance module's read contract instead of
 * joining into Finance tables. These tests lock that contract's output shape,
 * mirroring the prior-art GetStudentFeeSummaryQueryTest.
 */
function createHubFinanceStudent(string $studentCode = 'AUS200200'): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentCode,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    return [$student, $semester];
}

it('returns a read-only fees, gold, and scholarships summary through the Finance contract', function () {
    [$student, $semester] = createHubFinanceStudent();

    // Fees: one active charge invoiced for 15,000,000, of which 10,000,000 is paid.
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-HUB-1',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'hub_finance_summary',
        'source_ref' => 'hub-finance-summary:'.$student->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 15000000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 1 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 15000000,
        'description_snapshot' => 'EGC Level 1 Fee',
        'status' => 'active',
    ]);

    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 10000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 10000000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    // Gold wallet.
    StudentWallet::query()->create([
        'student_id' => $student->id,
        'balance' => 750,
    ]);

    // Scholarship.
    ScholarshipDefinition::query()->create([
        'code' => 'SCH-MERIT',
        'name' => 'Merit Scholarship',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::query()->create([
        'student_id' => $student->id,
        'scholarship_code' => 'SCH-MERIT',
        'awarded_at' => now()->toDateString(),
    ]);

    $summary = app(HubStudentFinanceSummaryReader::class)->summary($student->id);

    expect($summary)->toHaveKeys(['fees', 'gold', 'scholarships'])
        ->and($summary['fees']['net_charges'])->toBe(15000000.0)
        ->and($summary['fees']['total_paid'])->toBe(10000000.0)
        ->and($summary['fees']['outstanding'])->toBe(5000000.0)
        ->and($summary['fees']['status'])->toBe('outstanding')
        ->and($summary['gold']['balance'])->toBe(750)
        ->and($summary['scholarships'])->toHaveCount(1)
        ->and($summary['scholarships'][0]['code'])->toBe('SCH-MERIT')
        ->and($summary['scholarships'][0]['name'])->toBe('Merit Scholarship')
        ->and($summary['scholarships'][0]['amount'])->toBe(30.0);
});

it('returns zeroed, empty sections for a student with no finance records', function () {
    [$student] = createHubFinanceStudent('AUS200201');

    $summary = app(HubStudentFinanceSummaryReader::class)->summary($student->id);

    expect($summary['fees']['net_charges'])->toBe(0.0)
        ->and($summary['fees']['outstanding'])->toBe(0.0)
        ->and($summary['gold']['balance'])->toBe(0)
        ->and($summary['scholarships'])->toBe([]);
});
