<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\SettlementService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['create_finance_charges', 'void_finance_charges', 'view_finance_charges']);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

function createCompletedPayment(Student $student, float $amount): Payment
{
    return Payment::create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
}

function createCanonicalEgcCharge(string $description): FinanceCharge
{
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null,
        'source_system' => 'finance-test',
        'source_kind' => 'void-release-allocation',
        'source_ref' => 'egc:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 15_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'egc:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'egc:test'],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => $description,
    ]);
}

it('void charge inserts reversal payment applications and restores unapplied balance', function () {
    $settlementService = app(SettlementService::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge = createCanonicalEgcCharge('EGC Level 4');

    $line = InvoiceLine::where('charge_id', $charge->id)->firstOrFail();
    $payment = createCompletedPayment($this->student, 15000000);

    $settlementService->createPaymentApplication($payment, $line, 15000000, 'application', $this->user->id);

    expect(PaymentApplication::count())->toBe(1)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0);

    $result = $voidAction->handle($charge->id, 'Student only completed 1 level', $this->user->id);

    expect(PaymentApplication::count())->toBe(2)
        ->and((float) PaymentApplication::sum('amount'))->toBe(0.0)
        ->and($line->fresh()->status)->toBe('void')
        ->and($result['released_allocations'])->toBe(1)
        ->and($result['released_amount'])->toBe(15000000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(15000000.0);
});

it('void charge keeps invoice and recalculates snapshot from active lines', function () {
    $settlementService = app(SettlementService::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge1 = createCanonicalEgcCharge('EGC Level 4');
    $charge2 = createCanonicalEgcCharge('EGC Level 5');

    $invoice = StudentInvoice::firstOrFail();
    $invoice->update(['status' => 'pending']);
    $payment = createCompletedPayment($this->student, 30000000);

    $line1 = InvoiceLine::where('charge_id', $charge1->id)->firstOrFail();
    $line2 = InvoiceLine::where('charge_id', $charge2->id)->firstOrFail();

    $settlementService->createPaymentApplication($payment, $line1, 15000000, 'application', $this->user->id);
    $settlementService->createPaymentApplication($payment, $line2, 15000000, 'application', $this->user->id);

    $voidAction->handle($charge2->id, 'Only completed 1 level', $this->user->id);

    $invoice->refresh();

    expect(StudentInvoice::count())->toBe(1)
        ->and((float) $invoice->subtotal)->toBe(15000000.0)
        ->and((float) $invoice->discount_total)->toBe(0.0)
        ->and((float) $invoice->total_amount)->toBe(15000000.0)
        ->and((float) $invoice->paid_amount)->toBe(15000000.0)
        ->and($invoice->status)->toBe('paid')
        ->and($payment->fresh()->unapplied_amount)->toBe(15000000.0);
});

it('auto allocate writes payment applications only', function () {
    $autoAllocateAction = app(AutoAllocatePaymentsAction::class);

    createCanonicalEgcCharge('EGC Level 4');
    createCanonicalEgcCharge('EGC Level 5');

    $payment = createCompletedPayment($this->student, 30000000);

    $stats = $autoAllocateAction->run([
        FinanceCharge::TYPE_EGC_LEVEL_FEE,
    ], $this->user->id);

    expect($stats['students_processed'])->toBe(1)
        ->and($stats['allocations_created'])->toBe(2)
        ->and($stats['total_allocated_amount'])->toBe(30000000.0)
        ->and(PaymentApplication::count())->toBe(2)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0);
});

it('invoice discount allocation updates invoice totals without negative finance charges', function () {
    $invoiceService = app(InvoiceGenerationService::class);

    $charge1 = createCanonicalEgcCharge('EGC Level 4');
    $charge2 = createCanonicalEgcCharge('EGC Level 5');

    $invoice = StudentInvoice::firstOrFail();

    $discount = $invoiceService->applyInvoiceDiscount(
        $invoice,
        'voucher',
        5000000,
        'tests',
        'Voucher test',
        1,
        $this->user->id,
    );

    $line1 = InvoiceLine::where('charge_id', $charge1->id)->firstOrFail();
    $line2 = InvoiceLine::where('charge_id', $charge2->id)->firstOrFail();

    expect($discount)->toBeInstanceOf(InvoiceDiscount::class)
        ->and(DiscountAllocation::count())->toBe(1)
        ->and((float) DiscountAllocation::where('invoice_line_id', $line1->id)->sum('amount'))->toBe(5000000.0)
        ->and((float) DiscountAllocation::where('invoice_line_id', $line2->id)->sum('amount'))->toBe(0.0)
        ->and(FinanceCharge::query()->where('amount', '<', 0)->count())->toBe(0);

    $invoice->refresh();

    expect((float) $invoice->subtotal)->toBe(30000000.0)
        ->and((float) $invoice->discount_total)->toBe(5000000.0)
        ->and((float) $invoice->total_amount)->toBe(25000000.0);
});

it('void charge reassigns discount and releases excess payment from surviving line', function () {
    $invoiceService = app(InvoiceGenerationService::class);
    $settlementService = app(SettlementService::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge1 = createCanonicalEgcCharge('EGC Level 4');
    $charge2 = createCanonicalEgcCharge('EGC Level 5');

    $invoice = StudentInvoice::firstOrFail();
    $invoiceService->applyInvoiceDiscount($invoice, 'voucher', 5000000, 'tests', 'Voucher test', 1, $this->user->id);

    $line1 = InvoiceLine::where('charge_id', $charge1->id)->firstOrFail();
    $line2 = InvoiceLine::where('charge_id', $charge2->id)->firstOrFail();
    $payment = createCompletedPayment($this->student, 25000000);

    $settlementService->createPaymentApplication($payment, $line1, 10000000, 'application', $this->user->id);
    $settlementService->createPaymentApplication($payment, $line2, 15000000, 'application', $this->user->id);

    $result = $voidAction->handle($charge1->id, 'Voided after transfer', $this->user->id);

    $invoice->refresh();

    expect($line1->fresh()->status)->toBe('void')
        ->and((float) DiscountAllocation::where('invoice_line_id', $line1->id)->sum('amount'))->toBe(0.0)
        ->and((float) DiscountAllocation::where('invoice_line_id', $line2->id)->sum('amount'))->toBe(5000000.0)
        ->and((float) $invoice->subtotal)->toBe(15000000.0)
        ->and((float) $invoice->discount_total)->toBe(5000000.0)
        ->and((float) $invoice->total_amount)->toBe(10000000.0)
        ->and((float) $invoice->paid_amount)->toBe(10000000.0)
        ->and($result['released_amount'])->toBe(15000000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(15000000.0);
});
