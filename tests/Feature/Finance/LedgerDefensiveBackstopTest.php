<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\DiscountAllocation;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-02 / FIN-03 defensive backstop. The ledger is signed (reversal/release =
 * negative rows) so SUM already nets correctly in normal operation. These tests
 * pin the defense-in-depth behaviour: even if a future path marks a discount
 * reversed or a line voided WITHOUT writing the offsetting negative row, the
 * canonical balance must not silently inflate.
 */
function makeBackstopInvoice(): array
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

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-BACKSTOP-'.$student->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    return [$invoice, $line];
}

it('ignores discount allocations whose parent discount is reversed without a release row', function () {
    [$invoice, $line] = makeBackstopInvoice();

    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'tests',
        'description' => 'Reversed voucher',
        'amount' => 4000000,
        'reference_id' => 1,
        'status' => 'reversed',
    ]);

    // Lingering positive allocation with NO offsetting release row (the bug shape).
    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 4000000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $snapshot['discount'])->toBe(0.0)
        ->and((float) $snapshot['net'])->toBe(10000000.0);
});

it('keeps line outstanding consistent with the snapshot when a discount is reversed', function () {
    [$invoice, $line] = makeBackstopInvoice();

    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'tests',
        'description' => 'Reversed voucher',
        'amount' => 4000000,
        'reference_id' => 1,
        'status' => 'reversed',
    ]);

    // Lingering positive allocation with NO offsetting release row.
    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 4000000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    $settlement = app(SettlementService::class);
    $snapshotRemaining = $settlement->deriveInvoiceSnapshot($invoice->fresh())['remaining'];

    // Allocation path (line outstanding) must agree with the displayed balance,
    // otherwise allocation would stop early while the invoice still shows debt.
    expect((float) $settlement->getLineDiscountAmount($line->fresh()))->toBe(0.0)
        ->and((float) $settlement->getLineOutstandingAmount($line->fresh()))->toBe(10000000.0)
        ->and((float) $settlement->getLineOutstandingAmount($line->fresh()))->toBe((float) $snapshotRemaining);
});

it('still nets an active discount that was released by a signed negative row', function () {
    [$invoice, $line] = makeBackstopInvoice();

    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'tests',
        'description' => 'Voucher',
        'amount' => 4000000,
        'reference_id' => 1,
        'status' => 'active',
    ]);

    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 4000000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => -4000000,
        'entry_type' => 'release',
        'allocation_rule' => 'oldest_line_first',
    ]);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    // Net to zero discount via the signed rows; net stays full 10M.
    expect((float) $snapshot['discount'])->toBe(0.0)
        ->and((float) $snapshot['net'])->toBe(10000000.0);
});

it('excludes payment applications attached to a voided line', function () {
    [$invoice, $line] = makeBackstopInvoice();

    $payment = Payment::create([
        'student_id' => $invoice->student_id,
        'amount' => 6000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    // Payment applied, then the line is voided WITHOUT a reversal row (bug shape).
    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 6000000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $line->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => 'test']);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    // Voided line contributes nothing: no gross, no paid.
    expect((float) $snapshot['gross'])->toBe(0.0)
        ->and((float) $snapshot['paid'])->toBe(0.0)
        ->and((float) $snapshot['net'])->toBe(0.0);
});
