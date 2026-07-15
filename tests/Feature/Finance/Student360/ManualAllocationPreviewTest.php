<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Services\PermissionService;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantAllocPreview')) {
    function grantAllocPreview(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
});

it('previews a payment unapplied amount and candidate lines', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment_id', $payment->id)
        ->assertJsonPath('data.unapplied', 1000000)
        ->assertJsonStructure(['data' => ['payment_id', 'unapplied', 'candidates']]);
});

it('fails closed and returns canonical review evidence for an invalid candidate position', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-MANUAL-ALLOCATION-INVALID',
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
    $charge = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 1000000,
        'description' => 'Invalid legacy charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'Invalid legacy charge',
        'status' => 'active',
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")
        ->assertOk()
        ->assertJsonPath('data.candidates', [])
        ->assertJsonPath('data.settlement_position.valid', false)
        ->assertJsonPath('data.settlement_position.issues.0.code', 'settlement_position.missing_currency');
});

it('fails closed with stable evidence when a batch result omits a candidate line', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-MANUAL-ALLOCATION-MISSING-BATCH',
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
    $charge = FinanceCharge::create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 1000000,
        'description' => 'Missing batch candidate',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'Missing batch candidate',
        'status' => 'active',
    ]);
    $reader = Mockery::mock(SettlementPositionReader::class);
    $reader->shouldReceive('batch')->once()->andReturn([]);
    app()->instance(SettlementPositionReader::class, $reader);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")
        ->assertOk()
        ->assertJsonPath('data.candidates', [])
        ->assertJsonPath('data.settlement_position.valid', false)
        ->assertJsonPath('data.settlement_position.issues.0.code', SettlementPositionIssue::BATCH_CARDINALITY_MISMATCH)
        ->assertJsonPath('data.settlement_position.issues.0.evidence.payable_line_id', $line->id);
});

it('uses canonical gross, reductions, cash, credit, and remaining values for a candidate', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-MANUAL-ALLOCATION-COMPONENTS',
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'paid',
        'due_date' => now()->addDays(7),
    ]);
    $obligation = FinanceObligation::create([
        'source_system' => 'test',
        'source_kind' => 'manual_allocation_preview',
        'source_ref' => 'manual-allocation-preview:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 1000000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 1000000,
        'description' => 'Canonical allocation candidate',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'Canonical allocation candidate',
        'status' => 'active',
    ]);
    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'test',
        'description' => 'Canonical discount',
        'amount' => 200000,
        'status' => 'active',
        'approved_by' => $user->id,
    ]);
    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 200000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'test',
    ]);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 300000,
        'entry_type' => 'application',
        'applied_at' => now(),
        'created_by' => $user->id,
    ]);
    $credit = FinanceCreditEntitlement::create([
        'source_system' => 'test',
        'source_kind' => 'manual_allocation_preview',
        'source_ref' => 'manual-allocation-preview-credit:'.uniqid('', true),
        'entitlement_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED,
        'amount' => 100000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'approved_at' => now(),
    ]);
    CreditApplication::create([
        'finance_credit_entitlement_id' => $credit->id,
        'invoice_line_id' => $line->id,
        'amount' => 100000,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
        'created_by' => $user->id,
    ]);
    DB::table('student_invoices')->where('id', $invoice->id)->update(['status' => 'paid']);
    $this->assertDatabaseHas('student_invoices', ['id' => $invoice->id, 'status' => 'paid']);
    expect(app(SettlementService::class)
        ->getOutstandingLinesForStudent($this->student->id, AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)
        ->modelKeys())
        ->toContain($line->id);
    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")
        ->assertOk()
        ->assertJsonPath('data.settlement_position.valid', true)
        ->assertJsonPath('data.candidates.0.gross', 1000000)
        ->assertJsonPath('data.candidates.0.discount', 200000)
        ->assertJsonPath('data.candidates.0.cash_applied', 300000)
        ->assertJsonPath('data.candidates.0.credit_applied', 100000)
        ->assertJsonPath('data.candidates.0.remaining_collectible', 400000)
        ->assertJsonPath('data.candidates.0.would_apply', 400000);
});

it('denies preview without allocate permission', function () {
    $user = grantAllocPreview([]);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")->assertForbidden();
});

it('hides a cross-campus payment as not found', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $other = Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
    $payment = Payment::create([
        'student_id' => $other->id,
        'amount' => 1,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")->assertNotFound();
});
