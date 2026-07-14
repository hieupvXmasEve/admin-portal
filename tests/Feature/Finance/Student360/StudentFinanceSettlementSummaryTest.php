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
use App\Modules\Finance\Queries\Student360\GetStudentFinanceOverviewKpisQuery;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @return array{student: Student, semester: Semester, billing_account: BillingAccount} */
function makeStudentSettlementFixture(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->state([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ])->create();

    return [
        'student' => $student,
        'semester' => $semester,
        'billing_account' => BillingAccount::query()->where('student_id', $student->id)->firstOrFail(),
    ];
}

function createStudentSettlementLine(Student $student, Semester $semester, BillingAccount $billingAccount, string $amount = '10000000.00'): InvoiceLine
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-STUDENT-POSITION-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'student_position_summary',
        'source_ref' => 'student-position:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
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
        'amount' => $amount,
        'description' => 'Canonical student tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Canonical student tuition',
        'status' => 'active',
    ]);
}

it('uses canonical cash, credit, remaining, and unapplied-cash semantics for Student 360', function (): void {
    ['student' => $student, 'semester' => $semester, 'billing_account' => $billingAccount] = makeStudentSettlementFixture();
    $line = createStudentSettlementLine($student, $semester, $billingAccount);

    $matchedPayment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 4_000_000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $matchedPayment->id,
        'invoice_line_id' => $line->id,
        'amount' => 4_000_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);
    Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 3_000_000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    $entitlement = FinanceCreditEntitlement::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'student_position_summary',
        'source_ref' => 'credit:'.uniqid('', true),
        'entitlement_type' => FinanceEntitlementType::DeferCredit,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED,
        'amount' => 2_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'approved_at' => now(),
    ]);
    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $entitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => 2_000_000,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);

    $summary = app(StudentFinanceSettlementPositionReader::class)->current($student->id);
    $kpis = app(GetStudentFinanceOverviewKpisQuery::class)->handle($student->id);

    expect($summary['valid'])->toBeTrue()
        ->and($summary['gross'])->toBe(10_000_000.0)
        ->and($summary['cash_applied'])->toBe(4_000_000.0)
        ->and($summary['credit_applied'])->toBe(2_000_000.0)
        ->and($summary['remaining_collectible'])->toBe(4_000_000.0)
        ->and($summary['unapplied_cash'])->toBe(3_000_000.0)
        ->and($kpis['kpis']['collected']['amount'])->toBe(4_000_000.0)
        ->and($kpis['kpis']['credit_applied']['amount'])->toBe(2_000_000.0)
        ->and($kpis['kpis']['surplus']['amount'])->toBe(3_000_000.0);

    Sanctum::actingAs($student);

    $this->getJson('/api/v1/student/finance/invoices')
        ->assertOk()
        ->assertJsonPath('data.invoices.0.subtotal', 10_000_000)
        ->assertJsonPath('data.invoices.0.paid_amount', 4_000_000)
        ->assertJsonPath('data.invoices.0.credit_amount', 2_000_000)
        ->assertJsonPath('data.invoices.0.remaining', 4_000_000)
        ->assertJsonPath('data.invoices.0.settlement_position.valid', true);
});

it('fails closed with neutral money output when legacy payable evidence is not materialized', function (): void {
    ['student' => $student, 'semester' => $semester, 'billing_account' => $billingAccount] = makeStudentSettlementFixture();
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-LEGACY-STUDENT-POSITION',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5_000_000,
        'description' => 'Legacy tuition without obligation',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 5_000_000,
        'description_snapshot' => 'Legacy tuition without obligation',
        'status' => 'active',
    ]);

    $summary = app(StudentFinanceSettlementPositionReader::class)->current($student->id);

    expect($summary['valid'])->toBeFalse()
        ->and($summary['gross'])->toBeNull()
        ->and($summary['remaining_collectible'])->toBeNull()
        ->and($summary['money_actions_available'])->toBeFalse()
        ->and($summary['student_message'])->toBe(StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE);
});

it('returns the same neutral contract from the student API summary', function (): void {
    ['student' => $student] = makeStudentSettlementFixture();
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/student/finance/overview')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.balance.settlement_position.valid', true)
        ->assertJsonPath('data.balance.total_paid', 0)
        ->assertJsonPath('data.balance.applied_credit', 0)
        ->assertJsonPath('data.balance.balance', 0);
});
