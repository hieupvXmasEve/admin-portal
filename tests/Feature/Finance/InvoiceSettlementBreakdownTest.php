<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();
    $this->user = User::factory()->create();
    grantFinance($this->user, ['view_finance_invoices'], $this->campus);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn (): Campus => $this->campus);
});

/** @return array{0:Student,1:StudentInvoice,2:BillingAccount} */
function makeInvoiceSettlementFixture(object $context): array
{
    $student = Student::factory()
        ->forCampus($context->campus)
        ->forProgram($context->program)
        ->state([
            'student_id' => 'INV-SP-'.uniqid(),
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-SP-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
        'cached_subtotal' => 99999999,
        'cached_discount_total' => 88888888,
        'cached_total_amount' => 77777777,
        'cached_paid_amount' => 66666666,
    ]);

    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);

    return [$student, $invoice, $account];
}

function makeInvoiceSettlementLine(
    StudentInvoice $invoice,
    BillingAccount $account,
    string $amount,
    string $type = FinanceCharge::TYPE_TUITION_TERM,
): InvoiceLine {
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test',
        'source_kind' => 'invoice_settlement_breakdown',
        'source_ref' => 'invoice-settlement-breakdown:'.uniqid('', true),
        'obligation_type' => $type,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'invoice-settlement-breakdown:test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $invoice->student_id,
        'semester_id' => $invoice->semester_id,
        'charge_type' => $type,
        'amount' => $amount,
        'description' => 'Invoice settlement breakdown charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Invoice settlement breakdown line',
        'status' => 'active',
    ]);
}

function applyInvoiceSettlementCash(InvoiceLine $line, string $amount, ?string $paymentAmount = null): Payment
{
    $payment = Payment::query()->create([
        'student_id' => $line->invoice()->value('student_id'),
        'amount' => $paymentAmount ?? $amount,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'invoice-settlement-breakdown',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    return $payment;
}

function applyInvoiceSettlementDiscount(StudentInvoice $invoice, InvoiceLine $line, string $amount): void
{
    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'invoice-settlement-breakdown',
        'description' => 'Invoice settlement breakdown discount',
        'amount' => $amount,
        'status' => 'active',
    ]);
    DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'allocation',
        'allocation_rule' => 'invoice-settlement-breakdown',
    ]);
}

function applyInvoiceSettlementCredit(InvoiceLine $line, string $amount, string $reversal = '0.00'): void
{
    $entitlement = FinanceCreditEntitlement::query()->create([
        'billing_account_id' => $line->charge->financeObligation->billing_account_id,
        'source_system' => 'test',
        'source_kind' => 'invoice_settlement_breakdown',
        'source_ref' => 'invoice-settlement-credit:'.uniqid('', true),
        'entitlement_type' => FinanceEntitlementType::DeferCredit,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'invoice-settlement-breakdown:test',
        'pricing_snapshot' => [],
        'approved_at' => now(),
    ]);
    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $entitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);

    if ($reversal !== '0.00') {
        CreditApplication::query()->create([
            'finance_credit_entitlement_id' => $entitlement->id,
            'invoice_line_id' => $line->id,
            'amount' => '-'.$reversal,
            'entry_type' => CreditApplication::ENTRY_REVERSAL,
            'applied_at' => now(),
        ]);
    }
}

it('renders invoice detail from one canonical position and exact line breakdown', function (): void {
    [$student, $invoice, $account] = makeInvoiceSettlementFixture($this);
    $mixedLine = makeInvoiceSettlementLine($invoice, $account, '1000000.00');
    $settledLine = makeInvoiceSettlementLine($invoice, $account, '500000.00', FinanceCharge::TYPE_MANUAL_FEE);
    applyInvoiceSettlementDiscount($invoice, $mixedLine, '100000.00');
    applyInvoiceSettlementCash($mixedLine, '300000.00');
    applyInvoiceSettlementCredit($mixedLine, '200000.00', '50.00');
    $surplusPayment = applyInvoiceSettlementCash($settledLine, '500000.00', '700000.00');
    PaymentSurplusDisposition::query()->create([
        'payment_id' => $surplusPayment->id,
        'idempotency_key' => (string) Str::uuid(),
        'type' => PaymentSurplusDisposition::TYPE_REFUND,
        'amount' => '50000.00',
        'evidence' => [],
        'audit_signature' => str_repeat('a', 64),
        'approved_by' => $this->user->id,
        'disposed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('finance.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Invoices/Show')
            ->where('invoice.student.id', $student->id)
            ->where('invoice.settlement_position.valid', true)
            ->where('invoice.settlement_position.amounts.gross.amount', '1500000.00')
            ->where('invoice.settlement_position.amounts.discount.amount', '100000.00')
            ->where('invoice.settlement_position.amounts.cash.amount', '800000.00')
            ->where('invoice.settlement_position.amounts.credit.amount', '199950.00')
            ->where('invoice.settlement_position.amounts.remaining.amount', '400050.00')
            ->where('invoice.settlement_position.breakdown_reconciliation.status', 'exact')
            ->where('invoice.settlement_position.breakdown_reconciliation.rounding_remainder.amount', '0.00')
            ->has('invoice.settlement_position.payable_line_breakdown', 2)
            ->where('invoice.charges.0.amounts.gross.amount', '1000000.00')
            ->where('invoice.charges.0.amounts.discount.amount', '100000.00')
            ->where('invoice.charges.0.amounts.cash.amount', '300000.00')
            ->where('invoice.charges.0.amounts.credit.amount', '199950.00')
            ->where('invoice.charges.0.amounts.remaining.amount', '400050.00')
            ->where('invoice.charges.1.amounts.remaining.amount', '0.00')
            ->where('invoice.settlement_entries', fn ($entries): bool => collect($entries)->contains(
                fn (array $entry): bool => (int) ($entry['payment']['surplus_amount'] ?? 0) === 150000,
            ))
        );
});

it('hides untrusted money amounts when the invoice position is invalid', function (): void {
    [, $invoice, $account] = makeInvoiceSettlementFixture($this);
    $line = makeInvoiceSettlementLine($invoice, $account, '1000000.00');
    applyInvoiceSettlementCash($line, '1200000.00');

    $this->actingAs($this->user)
        ->get(route('finance.invoices.show', $invoice))
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Invoices/Show')
            ->where('invoice.settlement_position.valid', false)
            ->where('invoice.settlement_position.amounts', null)
            ->where('invoice.settlement_position.settlement_state', 'invalid')
            ->where('invoice.settlement_position.issues.0.code', 'settlement_position.cash_exceeds_net_due')
            ->where('invoice.settlement_position.issues.0.evidence.cash', '1200000.00')
            ->where('invoice.charges.0.amounts', null)
        );
});

it('keeps the staff invoice renderer on approved settlement wording and out of local balance arithmetic', function (): void {
    $view = file_get_contents(resource_path('js/pages/Finance/Invoices/Show.vue'));

    expect($view)
        ->toContain('Credit đã áp dụng')
        ->toContain('Còn phải thu')
        ->toContain('Còn dư')
        ->toContain('Cần kiểm tra')
        ->not->toContain('invoice.outstanding_balance')
        ->not->toContain('invoice.total_amount - invoice.paid_amount');
});
