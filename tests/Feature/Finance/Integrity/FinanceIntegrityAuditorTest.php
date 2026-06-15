<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use App\Modules\Finance\Support\Integrity\FinanceInvariant;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeInvoiceLineForStudent(Student $student): InvoiceLine
{
    $semester = Semester::factory()->create();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1000,
        'description' => 'Audit fixture charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-AUD-'.$student->id.'-'.$charge->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => 1000,
        'discount_total' => 0,
        'total_amount' => 1000,
        'paid_amount' => 0,
    ]);

    return InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000,
        'description_snapshot' => 'Audit fixture line',
        'status' => 'active',
    ]);
}

function seedOverAllocatedPayment(Student $student): Payment
{
    // Payment whose applications sum (250) exceeds its amount (100) -> violates INV-1.
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 100,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => makeInvoiceLineForStudent($student)->id,
        'amount' => 250,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    return $payment;
}

function auditStudent(Campus $campus, Program $program, Semester $semester): Student
{
    return Student::factory()->forCampus($campus)->forProgram($program)
        ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
        ->create();
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
});

it('summarize() global counts INV-1 over-allocation', function () {
    $student = auditStudent($this->campus, $this->program, $this->semester);
    seedOverAllocatedPayment($student);

    $summary = collect(app(FinanceIntegrityAuditor::class)->summarize(null))
        ->keyBy('code');

    expect($summary['INV-1']['count'])->toBe(1)
        ->and($summary['INV-1']['error'])->toBeNull();
});

it('findForScope() returns INV-1 only for the offending student', function () {
    $bad = auditStudent($this->campus, $this->program, $this->semester);
    $good = auditStudent($this->campus, $this->program, $this->semester);
    seedOverAllocatedPayment($bad);

    $findingsBad = app(FinanceIntegrityAuditor::class)
        ->findForScope(new FinanceAuditScope(studentIds: [$bad->id]));
    $findingsGood = app(FinanceIntegrityAuditor::class)
        ->findForScope(new FinanceAuditScope(studentIds: [$good->id]));

    expect(collect($findingsBad)->pluck('code'))->toContain('INV-1')
        ->and(collect($findingsGood)->pluck('code'))->not->toContain('INV-1');
});

it('findForScope() returns nothing for an empty scope rather than scanning globally', function () {
    $student = auditStudent($this->campus, $this->program, $this->semester);
    seedOverAllocatedPayment($student);

    expect(app(FinanceIntegrityAuditor::class)->findForScope(new FinanceAuditScope()))->toBe([]);
});

it('surfaces a failing invariant as an error, never a false clean result', function () {
    // Registry whose single invariant points at a non-existent table.
    $broken = new class extends FinanceInvariantRegistry
    {
        public function all(): array
        {
            return [new FinanceInvariant(
                'INV-1', 'CRITICAL', 'broken',
                'SELECT COUNT(*) c FROM nonexistent_audit_table WHERE {scope}',
                'SELECT id FROM nonexistent_audit_table WHERE {scope} LIMIT 5',
                'student_id IN ({ids})',
            )];
        }
    };
    $auditor = new FinanceIntegrityAuditor($broken);

    $summary = collect($auditor->summarize(null))->keyBy('code');
    expect($summary['INV-1']['count'])->toBeNull()
        ->and($summary['INV-1']['error'])->not->toBeNull();

    $findings = $auditor->findForScope(new FinanceAuditScope(studentIds: [1]));
    expect(collect($findings)->firstWhere('code', 'INV-1')['kind'])->toBe('invariant_error');
});
