<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\StudentFeeSummaryReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAcademicFeeStudent(string $studentCode = 'AUS118115'): array
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

it('builds fee summary from invoice discounts and payment applications only', function () {
    [$student, $semester] = createAcademicFeeStudent();

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-FEE-1',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $charge = FinanceCharge::query()->create([
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

    InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'App\\Models\\VoucherApplication',
        'description' => 'Voucher Applied (VC-001)',
        'amount' => 5000000,
        'reference_id' => 1,
    ]);

    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 25000000,
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

    $summary = app(StudentFeeSummaryReader::class)->execute((int) $student->id);

    expect($summary['summary']['total_discount'])->toBe(5000000.0)
        ->and($summary['summary']['active_due'])->toBe(10000000.0)
        ->and($summary['summary']['total_allocated'])->toBe(10000000.0)
        ->and($summary['summary']['unapplied_balance'])->toBe(15000000.0)
        ->and($summary['payments'][0]['allocated_amount'])->toBe(10000000.0)
        ->and($summary['payments'][0]['unapplied_amount'])->toBe(15000000.0)
        ->and($summary['payments'][0]['allocations'])->toHaveCount(1)
        ->and($summary['billing_by_semester'][0]['invoices'][0]['payments'][0]['charges'][0]['invoice_line_id'])->toBe($line->id)
        ->and($summary['billing_by_semester'])->toHaveCount(1)
        ->and($summary['billing_by_semester'][0]['totals']['total'])->toBe(10000000.0)
        ->and($summary['billing_by_semester'][0]['totals']['paid'])->toBe(10000000.0)
        ->and($summary['billing_by_semester'][0]['invoices'][0]['discount_total'])->toBe(5000000.0)
        ->and(collect($summary['billing_by_semester'][0]['invoices'][0]['lines'])->contains(fn (array $lineSummary) => $lineSummary['type'] === 'Discount' && $lineSummary['item'] === 'Voucher Applied (VC-001)' && $lineSummary['amount'] === -5000000.0))->toBeTrue();
});
