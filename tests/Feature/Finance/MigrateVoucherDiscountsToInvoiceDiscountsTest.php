<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\DiscountAllocation;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\VoucherApplication;
use App\Models\VoucherDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function createVoucherMigrationStudent(string $studentCode = 'AUS118115'): Student
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    return Student::factory()
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
}

function createVoucherDefinition(string $code = 'VC-001'): VoucherDefinition
{
    return VoucherDefinition::query()->create([
        'code' => $code,
        'name' => 'Voucher '.$code,
        'description' => 'Test voucher',
        'voucher_type' => 'discount',
        'discount_type' => 'fixed_amount',
        'discount_value' => 5000000,
        'max_discount_amount' => 5000000,
        'is_stackable' => false,
        'max_uses_per_student' => 1,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_until' => now()->addDay()->toDateString(),
        'is_active' => true,
    ]);
}

function createInvoice(Student $student, Semester $semester, string $number = 'INV-TEST-1'): StudentInvoice
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

it('supports dry run without writing invoice discounts', function () {
    $student = createVoucherMigrationStudent('AUS118115');
    $semester = Semester::factory()->active()->create();
    $invoice = createInvoice($student, $semester, 'INV-DRY-1');
    $voucher = createVoucherDefinition('VC-DRY');

    VoucherApplication::query()->create([
        'voucher_definition_id' => $voucher->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_id' => $invoice->id,
        'status' => 'applied',
        'applied_at' => now(),
        'discount_amount' => 5000000,
    ]);

    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 15000000,
        'description_snapshot' => 'EGC Level Fee',
        'status' => 'active',
    ]);

    $this->artisan('finance:migrate-voucher-discounts', [
        '--dry-run' => true,
        '--student' => 'AUS118115',
    ])
        ->expectsOutputToContain('Voucher discount migration preview')
        ->expectsOutputToContain('DRY RUN MODE')
        ->assertExitCode(0);

    expect(InvoiceDiscount::query()->count())->toBe(0)
        ->and(DiscountAllocation::query()->count())->toBe(0);
});

it('creates and updates voucher invoice discounts and backfills discount allocations idempotently', function () {
    $student = createVoucherMigrationStudent('AUS118116');
    $semester = Semester::factory()->active()->create();
    $invoice = createInvoice($student, $semester, 'INV-REAL-1');
    $voucher = createVoucherDefinition('VC-REAL');
    $otherSemester = Semester::factory()->active()->create();

    $application = VoucherApplication::query()->create([
        'voucher_definition_id' => $voucher->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_id' => $invoice->id,
        'status' => 'applied',
        'applied_at' => now(),
        'discount_amount' => 5000000,
    ]);

    $existingDiscount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'legacy',
        'description' => 'Old value',
        'amount' => 1000000,
        'reference_id' => $application->id,
    ]);

    if (Schema::hasColumn('invoice_discounts', 'status')) {
        $existingDiscount->forceFill(['status' => 'reversed'])->save();
    }

    VoucherApplication::query()->create([
        'voucher_definition_id' => $voucher->id,
        'student_id' => $student->id,
        'semester_id' => $otherSemester->id,
        'invoice_id' => null,
        'status' => 'applied',
        'applied_at' => now(),
        'discount_amount' => 4000000,
    ]);

    $chargeOne = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 3000000,
        'description' => 'EGC Level 1',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $chargeTwo = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 4000000,
        'description' => 'EGC Level 2',
        'effective_at' => now()->addSecond(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $lineOne = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $chargeOne->id,
        'amount_snapshot' => 3000000,
        'description_snapshot' => 'EGC Level 1',
        'status' => 'active',
    ]);

    $lineTwo = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $chargeTwo->id,
        'amount_snapshot' => 4000000,
        'description_snapshot' => 'EGC Level 2',
        'status' => 'active',
    ]);

    $this->artisan('finance:migrate-voucher-discounts', [
        '--force' => true,
        '--student' => 'AUS118116',
    ])->assertExitCode(0);

    expect(InvoiceDiscount::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->count())->toBe(2);

    $discount = InvoiceDiscount::query()->firstOrFail();

    expect($discount->discount_source)->toBe(VoucherApplication::class)
        ->and($discount->description)->toBe('Voucher Applied (VC-REAL)')
        ->and((float) $discount->amount)->toBe(5000000.0)
        ->and((int) $discount->reference_id)->toBe($application->id);

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

    if (Schema::hasColumn('invoice_discounts', 'status')) {
        expect($discount->status)->toBe('active');
    }

    $this->artisan('finance:migrate-voucher-discounts', [
        '--force' => true,
        '--student' => 'AUS118116',
    ])->assertExitCode(0);

    expect(InvoiceDiscount::query()->count())->toBe(1)
        ->and(DiscountAllocation::query()->count())->toBe(2);
});
