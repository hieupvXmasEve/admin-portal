<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @return array{student: Student, invoice: StudentInvoice, line: InvoiceLine, account: BillingAccount} */
function studentFinancePresentationFixture(string $invoiceStatus = 'pending'): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->state([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ])->create();
    $account = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-STUDENT-PRESENTATION-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => $invoiceStatus,
        'due_date' => now()->addDays(30),
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test',
        'source_kind' => 'student_finance_presentation',
        'source_ref' => 'presentation:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 10_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10_000_000,
        'description' => 'Canonical tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 10_000_000,
        'description_snapshot' => 'Canonical tuition',
        'status' => 'active',
    ]);

    return compact('student', 'invoice', 'line', 'account');
}

function applyStudentFinancePresentationCash(Student $student, InvoiceLine $line, float $amount): void
{
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'import',
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
}

function applyStudentFinancePresentationCredit(BillingAccount $account, InvoiceLine $line, float $amount): void
{
    $credit = FinanceCreditEntitlement::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test',
        'source_kind' => 'student_finance_presentation',
        'source_ref' => 'credit:'.uniqid('', true),
        'entitlement_type' => FinanceEntitlementType::DeferCredit,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'approved_at' => now(),
    ]);
    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $credit->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);
}

it('uses the same canonical partial cash and credit amounts across student reads', function (): void {
    ['student' => $student, 'invoice' => $invoice, 'line' => $line, 'account' => $account] = studentFinancePresentationFixture();
    applyStudentFinancePresentationCash($student, $line, 4_000_000);
    applyStudentFinancePresentationCredit($account, $line, 2_000_000);
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/student/finance/overview')
        ->assertOk()
        ->assertJsonPath('data.balance.total_charges', 10_000_000)
        ->assertJsonPath('data.balance.total_paid', 4_000_000)
        ->assertJsonPath('data.balance.applied_credit', 2_000_000)
        ->assertJsonPath('data.balance.balance', 4_000_000);
    $this->getJson('/api/v1/student/finance/balance')
        ->assertOk()
        ->assertJsonPath('data.balance.total_charges', 10_000_000)
        ->assertJsonPath('data.balance.total_paid', 4_000_000)
        ->assertJsonPath('data.balance.applied_credit', 2_000_000)
        ->assertJsonPath('data.balance.balance', 4_000_000);
    $this->getJson('/api/v1/student/finance/charges')
        ->assertOk()
        ->assertJsonPath('data.charges.0.amount', 10_000_000)
        ->assertJsonPath('data.charges.0.paid_amount', 4_000_000)
        ->assertJsonPath('data.charges.0.credit_amount', 2_000_000)
        ->assertJsonPath('data.charges.0.balance', 4_000_000)
        ->assertJsonPath('data.summary.remaining_amount', 4_000_000);
    $this->getJson("/api/v1/student/finance/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.paid_amount', 4_000_000)
        ->assertJsonPath('data.credit_amount', 2_000_000)
        ->assertJsonPath('data.remaining', 4_000_000)
        ->assertJsonPath('data.status', 'open');
});

it('never lets a cached invoice status present an invalid position as paid', function (): void {
    ['student' => $student, 'invoice' => $invoice, 'line' => $line] = studentFinancePresentationFixture('paid');
    applyStudentFinancePresentationCash($student, $line, 12_000_000);
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/student/finance/overview')
        ->assertOk()
        ->assertJsonPath('data.balance.balance', null)
        ->assertJsonPath('data.balance.status', 'invalid')
        ->assertJsonPath('data.balance.settlement_position.valid', false);
    $this->getJson('/api/v1/student/finance/balance')
        ->assertOk()
        ->assertJsonPath('data.balance.balance', null)
        ->assertJsonPath('data.balance.status', 'invalid')
        ->assertJsonPath('data.balance.settlement_position.valid', false);
    $this->getJson('/api/v1/student/finance/charges')
        ->assertOk()
        ->assertJsonPath('data.charges.0.amount', null)
        ->assertJsonPath('data.charges.0.paid_amount', null)
        ->assertJsonPath('data.charges.0.is_fully_paid', false)
        ->assertJsonPath('data.charges.0.settlement_position.valid', false)
        ->assertJsonPath('data.summary.total_charges', null);
    $this->getJson('/api/v1/student/finance/invoices')
        ->assertOk()
        ->assertJsonPath('data.invoices.0.total_amount', null)
        ->assertJsonPath('data.invoices.0.paid_amount', null)
        ->assertJsonPath('data.invoices.0.status', 'invalid')
        ->assertJsonPath('data.invoices.0.settlement_position.valid', false)
        ->assertJsonPath('data.summary.total_outstanding', null);
    $this->getJson("/api/v1/student/finance/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.total_amount', null)
        ->assertJsonPath('data.status', 'invalid')
        ->assertJsonPath('data.lines.0.payments', [])
        ->assertJsonPath('data.settlement_position.valid', false)
        ->assertJsonPath('data.settlement_position.issues.0.code', 'settlement_position.cash_exceeds_net_due');
});

it('keeps paid and void invoice states distinct only after canonical validation', function (): void {
    ['student' => $student, 'invoice' => $invoice, 'line' => $line] = studentFinancePresentationFixture();
    applyStudentFinancePresentationCash($student, $line, 10_000_000);
    Sanctum::actingAs($student);

    $this->getJson("/api/v1/student/finance/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.remaining', 0)
        ->assertJsonPath('data.status', 'paid');

    $invoice->update(['status' => 'cancelled']);

    $this->getJson("/api/v1/student/finance/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.settlement_position.valid', true)
        ->assertJsonPath('data.status', 'cancelled');
});

it('returns an unavailable representation when a student has no billing account', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->state([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ])->create();
    BillingAccount::query()->where('student_id', $student->id)->delete();
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/student/finance/overview')
        ->assertOk()
        ->assertJsonPath('data.balance.total_charges', null)
        ->assertJsonPath('data.balance.settlement_position.valid', false)
        ->assertJsonPath('data.balance.settlement_position.issues.0.code', 'settlement_position.missing_billing_account');
});

it('validates student-finance filters through dedicated requests', function (): void {
    ['student' => $student] = studentFinancePresentationFixture();
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/student/finance/charges?unpaid=not-a-boolean')->assertUnprocessable();
    $this->getJson('/api/v1/student/finance/invoices?status=unknown')->assertUnprocessable();
});
