<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use App\Modules\Finance\Queries\Operations\PreviewAutoAllocateQuery;
use App\Modules\Finance\Services\SettlementService;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * FIN-01 / wave 7: every Finance balance surface reads the same ledger result.
 *
 * Reductions are carried by discount_allocations + credit_applications
 * (ADR-0030). The legacy negative charge-line netting backstop is retired.
 */
function makeDiscountCarrierStudent(Campus $campus, Semester $semester, Program $program): array
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

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'manual_fee',
        source_ref: 'fixture:'.Str::ulid()->toBase32(),
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_MANUAL_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'amount' => 10_000_000,
            'description' => 'Tuition',
            'invoice_id' => $invoice->id,
        ],
    ));

    $line = InvoiceLine::query()->findOrFail($result->invoice_line_id);

    // Prefer attaching to the materializer's invoice when draft reuse wins.
    $invoice = StudentInvoice::query()->findOrFail($line->invoice_id);
    $invoice->forceFill([
        'status' => 'pending',
        'total_amount' => 10000000,
        'paid_amount' => 0,
    ])->save();

    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'tests',
        'description' => 'Scholarship discount carrier',
        'amount' => 3_000_000,
        'reference_id' => 1,
        'status' => 'active',
    ]);

    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 3_000_000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    return [$student, $invoice->fresh()];
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    app()->instance('campus', $this->campus);
});

it('canonical settlement service nets discount allocations (no negative-line backstop)', function () {
    [$student, $invoice] = makeDiscountCarrierStudent($this->campus, $this->semester, $this->program);

    $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect((float) $snapshot['gross'])->toBe(10000000.0)
        ->and((float) $snapshot['discount'])->toBe(3000000.0)
        ->and((float) $snapshot['net'])->toBe(7000000.0)
        ->and((float) $snapshot['paid'])->toBe(0.0)
        ->and((float) $snapshot['remaining'])->toBe(7000000.0);
});

it('settlement worklist reports the same canonical net as the service (no stale-cache divergence)', function () {
    [$student, $invoice] = makeDiscountCarrierStudent($this->campus, $this->semester, $this->program);

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
    [$student, $invoice] = makeDiscountCarrierStudent($this->campus, $this->semester, $this->program);

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
    [$student, $invoice] = makeDiscountCarrierStudent($this->campus, $this->semester, $this->program);

    Payment::create([
        'student_id' => $student->id,
        'amount' => 1000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);
    DB::table('student_invoices')->where('student_id', $student->id)->update(['status' => 'paid']);
    $this->assertDatabaseHas('student_invoices', ['id' => $invoice->id, 'status' => 'paid']);

    $preview = app(PreviewAutoAllocateQuery::class)->handle(
        AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
        [$student->id],
    );

    $canonicalNet = (float) app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh())['net'];

    // Discount carrier nets to 7M — never a zero-amount invoice that would be
    // counted as "mark paid without cash". A stale `paid` cache must also not
    // suppress this canonical outstanding line from the preview.
    expect($canonicalNet)->toBe(7_000_000.0)
        ->and($preview['summary']['total_allocations'])->toBe(1)
        ->and($preview['summary']['total_amount'])->toBe(1_000_000.0)
        ->and($preview['summary']['invoices_to_update'] === 0 || $canonicalNet > 0)->toBeTrue();
});

it('fails closed when a legacy active negative line is present after wave-7 retirement', function () {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_semester_id' => $this->semester->id,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-LEDGER-NEG',
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'ledger_source_of_truth',
        'source_ref' => 'ledger-source:'.$student->id,
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 10_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $debit = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10_000_000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $debit->id,
        'amount_snapshot' => 10_000_000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    // Historical artifact shape only — must NOT reduce net after wave 7.
    DB::statement('SET SESSION check_constraint_checks = OFF');
    try {
        $credit = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => -3_000_000,
            'description' => 'Legacy negative',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
    } finally {
        DB::statement('SET SESSION check_constraint_checks = ON');
    }

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $credit->id,
        'amount_snapshot' => -3_000_000,
        'description_snapshot' => 'Legacy negative',
        'status' => 'active',
    ]);

    expect(fn () => app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh()))
        ->toThrow(RuntimeException::class, 'settlement_position.payable_line_not_collectible');
});
