<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\DiscountAllocation;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentScholarshipAward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function createScholarshipMigrationStudent(string $studentCode = 'AUS200001', string $status = 'intake_course'): array
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
            'status' => $status,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    return [$student, $semester];
}

function createScholarshipDefinition(string $code = 'SC-001', string $type = 'fixed_amount', int $amount = 5000000): ScholarshipDefinition
{
    return ScholarshipDefinition::query()->create([
        'code' => $code,
        'name' => 'Scholarship '.$code,
        'description' => 'Test scholarship',
        'type' => $type,
        'amount' => $amount,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_until' => now()->addDay()->toDateString(),
        'is_active' => true,
    ]);
}

function createScholarshipInvoice(Student $student, Semester $semester, string $number = 'INV-SCH-1'): StudentInvoice
{
    return StudentInvoice::query()->create([
        'invoice_number' => $number,
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);
}

it('supports dry run without writing scholarship discounts or allocations', function () {
    [$student, $semester] = createScholarshipMigrationStudent('AUS200010');
    $invoice = createScholarshipInvoice($student, $semester, 'INV-SCH-DRY');
    $definition = createScholarshipDefinition('SC-DRY');

    StudentScholarshipAward::query()->create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 15000000,
        'description' => 'Major Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 15000000,
        'description_snapshot' => 'Major Tuition',
        'status' => 'active',
    ]);

    $this->artisan('finance:migrate-scholarship-discounts', [
        '--dry-run' => true,
        '--student' => 'AUS200010',
    ])
        ->expectsOutputToContain('Scholarship discount migration preview')
        ->expectsOutputToContain('DRY RUN MODE')
        ->assertExitCode(0);

    expect(InvoiceDiscount::query()->count())->toBe(0)
        ->and(DiscountAllocation::query()->count())->toBe(0);
});

it('creates scholarship invoice discounts and backfills allocations using oldest_line_first', function () {
    [$student, $semester] = createScholarshipMigrationStudent('AUS200011', 'deferred');
    $invoice = createScholarshipInvoice($student, $semester, 'INV-SCH-REAL');
    $definition = createScholarshipDefinition('SC-REAL', 'fixed_amount', 5000000);

    $award = StudentScholarshipAward::query()->create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $chargeOne = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 3000000,
        'description' => 'Major Tuition 1',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $chargeTwo = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 4000000,
        'description' => 'Major Tuition 2',
        'effective_at' => now()->addSecond(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $lineOne = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $chargeOne->id,
        'amount_snapshot' => 3000000,
        'description_snapshot' => 'Major Tuition 1',
        'status' => 'active',
    ]);

    $lineTwo = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $chargeTwo->id,
        'amount_snapshot' => 4000000,
        'description_snapshot' => 'Major Tuition 2',
        'status' => 'active',
    ]);

    $this->artisan('finance:migrate-scholarship-discounts', [
        '--force' => true,
        '--student' => 'AUS200011',
    ])->assertExitCode(0);

    expect(InvoiceDiscount::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->count())->toBe(2);

    $discount = InvoiceDiscount::query()->firstOrFail();

    expect($discount->discount_source)->toBe(StudentScholarshipAward::class)
        ->and($discount->description)->toBe('Scholarship: Scholarship SC-REAL')
        ->and((float) $discount->amount)->toBe(5000000.0)
        ->and((int) $discount->reference_id)->toBe($award->id);

    if (Schema::hasColumn('invoice_discounts', 'status')) {
        expect($discount->status)->toBe('active');
    }

    $allocations = DiscountAllocation::query()
        ->where('invoice_discount_id', $discount->id)
        ->orderBy('invoice_line_id')
        ->get();

    expect($allocations)->toHaveCount(2)
        ->and($allocations[0]->invoice_line_id)->toBe($lineOne->id)
        ->and((float) $allocations[0]->amount)->toBe(3000000.0)
        ->and($allocations[0]->allocation_rule)->toBe('oldest_line_first')
        ->and($allocations[1]->invoice_line_id)->toBe($lineTwo->id)
        ->and((float) $allocations[1]->amount)->toBe(2000000.0)
        ->and($allocations[1]->allocation_rule)->toBe('oldest_line_first');

    $this->artisan('finance:migrate-scholarship-discounts', [
        '--force' => true,
        '--student' => 'AUS200011',
    ])->assertExitCode(0);

    expect(InvoiceDiscount::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->count())->toBe(2);
});
