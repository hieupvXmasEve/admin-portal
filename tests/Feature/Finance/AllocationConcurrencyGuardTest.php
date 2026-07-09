<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-11 / DB-09: allocation must never apply a payment beyond its unapplied
 * balance, even when two allocation paths (DNG webhook bridge + batch
 * auto-allocate) target the same payment. The lock + recompute-under-lock
 * enforces this; here we assert the resulting over-allocation invariant (INV-1)
 * deterministically. True OS-thread interleaving is not simulated under the
 * RefreshDatabase single-connection harness, but the cap is verifiable: the
 * second allocation always sees the reduced unapplied amount.
 */
function makeAllocationStudent(int $chargeCount, float $chargeAmount): array
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
        'invoice_number' => 'INV-ALLOC-'.$student->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    $charges = [];
    foreach (range(1, $chargeCount) as $i) {
        $charge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => $chargeAmount,
            'description' => "Charge {$i}",
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $chargeAmount,
            'description_snapshot' => "Charge {$i}",
            'status' => 'active',
        ]);

        $charges[] = $charge;
    }

    return [$student, $charges];
}

it('caps allocatePayment total to the payment unapplied balance (no over-allocation)', function () {
    [$student, $charges] = makeAllocationStudent(2, 4000000); // two lines, 4M each

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000, // only 5M available
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    // Request 8M across the two lines; only 5M is unapplied.
    app(PaymentService::class)->allocatePayment($payment->id, [
        $charges[0]->id => 4000000,
        $charges[1]->id => 4000000,
    ]);

    $applied = (float) PaymentApplication::where('payment_id', $payment->id)->sum('amount');

    expect($applied)->toBe(5000000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0);
});

it('caps a single allocation to the line outstanding so a line is never overpaid', function () {
    [$student, $charges] = makeAllocationStudent(1, 10000000); // one line, 10M charge
    $line = InvoiceLine::where('charge_id', $charges[0]->id)->firstOrFail();

    // A 4M active discount reduces the line outstanding to 6M.
    $discount = InvoiceDiscount::create([
        'invoice_id' => $line->invoice_id,
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

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 10000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'dng',
    ]);

    // Request the full 10M against a line that only owes 6M (e.g. discount applied
    // after a DNG push). The excess must stay as unapplied credit, not overpay the line.
    app(PaymentService::class)->allocatePayment($payment->id, [$charges[0]->id => 10000000]);

    $lineApplied = (float) PaymentApplication::where('invoice_line_id', $line->id)->sum('amount');

    expect($lineApplied)->toBe(6000000.0)
        ->and(app(SettlementService::class)->getLineOutstandingAmount($line->fresh()))->toBe(0.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(4000000.0);
});

it('never applies a payment onto another student\'s invoice line', function () {
    [$studentA] = makeAllocationStudent(1, 5000000);
    [$studentB, $chargesB] = makeAllocationStudent(1, 5000000);

    $paymentA = Payment::create([
        'student_id' => $studentA->id,
        'amount' => 5000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    // Attempt to allocate student A's payment onto student B's charge/line.
    app(PaymentService::class)->allocatePayment($paymentA->id, [$chargesB[0]->id => 5000000]);

    $lineB = InvoiceLine::where('charge_id', $chargesB[0]->id)->firstOrFail();

    expect((float) PaymentApplication::where('payment_id', $paymentA->id)->sum('amount'))->toBe(0.0)
        ->and((float) PaymentApplication::where('invoice_line_id', $lineB->id)->sum('amount'))->toBe(0.0)
        ->and($paymentA->fresh()->unapplied_amount)->toBe(5000000.0);
});

it('never overpays one line when two different payments target it (batch + bridge)', function () {
    [$student, $charges] = makeAllocationStudent(1, 5000000); // one line, 5M outstanding
    $line = InvoiceLine::where('charge_id', $charges[0]->id)->firstOrFail();

    // Two separate payments, each enough to cover the line on its own.
    Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now()->subDay(),
        'source' => 'manual',
    ]);
    $paymentB = Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'dng',
    ]);

    // Batch consumes the oldest payment against the line (line now fully paid).
    app(AutoAllocatePaymentsAction::class)->runForStudents(
        [$student->id],
        AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
        null,
    );

    // DNG-bridge path then targets the SAME line with a different payment.
    app(PaymentService::class)->allocatePayment($paymentB->id, [$charges[0]->id => 5000000]);

    $lineApplied = (float) PaymentApplication::where('invoice_line_id', $line->id)->sum('amount');

    // The line owed only 5M; it must never receive more across the two payments.
    expect($lineApplied)->toBe(5000000.0)
        ->and(app(SettlementService::class)->getLineOutstandingAmount($line->fresh()))->toBe(0.0)
        ->and($paymentB->fresh()->unapplied_amount)->toBe(5000000.0);
});

it('prevents over-allocation when batch auto-allocate and the DNG bridge target the same payment', function () {
    [$student, $charges] = makeAllocationStudent(2, 5000000); // two lines, 5M each = 10M due

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000, // 5M available, less than the 10M due
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    // Batch path consumes the whole payment against the first outstanding line.
    app(AutoAllocatePaymentsAction::class)->runForStudents(
        [$student->id],
        AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
        null,
    );

    // DNG-bridge style path then tries to allocate the same payment again.
    app(PaymentService::class)->allocatePayment($payment->id, [
        $charges[1]->id => 5000000,
    ]);

    $applied = (float) PaymentApplication::where('payment_id', $payment->id)->sum('amount');

    // INV-1: total applied never exceeds the payment amount.
    expect($applied)->toBeLessThanOrEqual(5000000.0)
        ->and($applied)->toBe(5000000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0);
});
