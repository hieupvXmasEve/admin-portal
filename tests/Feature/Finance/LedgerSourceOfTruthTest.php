<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use App\Modules\Finance\Queries\Operations\PreviewAutoAllocateQuery;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * FIN-01: every Finance balance surface must read the one canonical ledger
 * result. The historical divergence appears on the "legacy negative credit
 * line" shape (a positive charge line plus a legacy negative amount_snapshot
 * credit line with no discount_allocations): the canonical service treats the
 * negative line as a discount (net = gross - |negative|), while the old
 * duplicate snapshots summed only positive lines (net = gross) and mixed the
 * stale cache via max(). They therefore reported different balances.
 */
function makeLegacyCreditStudent(Campus $campus, Semester $semester, Program $program): array
{
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

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

    // Stale cache deliberately set high to expose any max()-based cache mixing.
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-LEDGER-1',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 10000000,
        'paid_amount' => 0,
    ]);

    $debitCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    // A discount stored as a negative-amount DEBIT charge is a LEGACY shape that
    // current code no longer produces and that chk_finance_charges_amount_sign
    // (DB-06) now forbids on write. We still must verify the settlement reader
    // nets such pre-existing rows, so seed it with the CHECK disabled — legacy
    // data is allowed to exist, new writes are not.
    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $creditCharge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => -3000000,
            'description' => 'Legacy scholarship credit',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $debitCharge->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -3000000,
        'description_snapshot' => 'Legacy scholarship credit',
        'status' => 'active',
    ]);

    return [$student, $invoice];
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    app()->instance('campus', $this->campus);
});

it('canonical settlement service nets the legacy negative credit line', function () {
    [$student, $invoice] = makeLegacyCreditStudent($this->campus, $this->semester, $this->program);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $snapshot['gross'])->toBe(10000000.0)
        ->and((float) $snapshot['discount'])->toBe(3000000.0)
        ->and((float) $snapshot['net'])->toBe(7000000.0)
        ->and((float) $snapshot['paid'])->toBe(0.0)
        ->and((float) $snapshot['remaining'])->toBe(7000000.0);
});

it('settlement worklist reports the same canonical net as the service (no stale-cache divergence)', function () {
    [$student, $invoice] = makeLegacyCreditStudent($this->campus, $this->semester, $this->program);

    $canonicalNet = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh())['net'];

    $request = Request::create('/finance/operations/settlement', 'GET', [
        'readiness' => 'all',
        'dng_status' => 'all',
        'per_page' => 50,
        'page' => 1,
    ]);

    $result = app(ListSettlementWorklistQuery::class)->handle($request);

    $rows = collect($result['students']->items());
    $studentRow = $rows->firstWhere('student_id', $student->id);

    expect($studentRow)->not->toBeNull();

    $invoiceRow = collect($studentRow['invoices'])->firstWhere('id', $invoice->id);

    expect((float) $invoiceRow['total_amount'])->toBe($canonicalNet)
        ->and((float) $invoiceRow['total_amount'])->toBe(7000000.0);
});

it('auto-allocate zero-amount detection uses the canonical net', function () {
    [$student, $invoice] = makeLegacyCreditStudent($this->campus, $this->semester, $this->program);

    // Net is 7M (> 0): the legacy credit must not make the invoice look zero-amount.
    $action = app(AutoAllocatePaymentsAction::class);
    $reflection = new ReflectionMethod($action, 'deriveInvoiceSnapshot');
    $reflection->setAccessible(true);

    $snapshot = $reflection->invoke($action, $invoice->fresh()->load([
        'invoiceLines.charge',
        'invoiceLines.paymentApplications',
        'invoiceLines.discountAllocations',
    ]));

    expect((float) $snapshot['total_amount'])->toBe(7000000.0);
});

it('preview auto-allocate zero-amount detection uses the canonical net', function () {
    [$student, $invoice] = makeLegacyCreditStudent($this->campus, $this->semester, $this->program);

    Payment::create([
        'student_id' => $student->id,
        'amount' => 1000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $preview = app(PreviewAutoAllocateQuery::class)->handle(
        AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
        [$student->id],
    );

    // 7M net still outstanding -> not counted as an invoice to mark paid.
    expect($preview['summary']['invoices_to_update'])->toBe(0);
});
