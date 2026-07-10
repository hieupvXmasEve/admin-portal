<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\FinanceChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
            'student_id' => 'STD-ENT-01',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
});

/**
 * @return array{0: StudentInvoice, 1: InvoiceLine, 2: FinanceCharge}
 */
function seedPositiveTuitionCharge(Student $student, Semester $semester, float $amount = 10_000_000): array
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-CHG-SUM-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => $amount,
        'discount_total' => 0,
        'total_amount' => $amount,
        'paid_amount' => 0,
    ]);

    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    return [$invoice, $line, $charge];
}

/**
 * Summary shape returned by the student charges API.
 *
 * @return array{total_charges: float, total_credits: float, net_amount: float}
 */
function studentChargesSummary(Student $student, ?int $semesterId = null): array
{
    return app(FinanceChargeService::class)->getChargeSummary($student->id, $semesterId);
}

it('includes discount and credit entitlement carriers in charges summary without negative charge rows', function (): void {
    [$invoice, $line] = seedPositiveTuitionCharge($this->student, $this->semester, 10_000_000);

    $billingAccount = BillingAccount::query()->firstOrCreate(
        ['student_id' => $this->student->id],
    );

    $discountEntitlement = FinanceDiscountEntitlement::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'finance',
        'source_kind' => 'voucher_application',
        'source_ref' => 'voucher-app:summary-1',
        'entitlement_type' => FinanceCharge::TYPE_VOUCHER_CREDIT,
        'lifecycle_status' => FinanceDiscountEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED,
        'amount' => 2_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['semester_id' => $this->semester->id],
        'approved_at' => now(),
    ]);

    $invoiceDiscount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'finance_discount_entitlement_id' => $discountEntitlement->id,
        'discount_type' => 'voucher',
        'discount_source' => 'App\\Models\\VoucherApplication',
        'description' => 'Voucher Applied',
        'amount' => 2_000_000,
        'reference_id' => 1,
        'status' => 'active',
    ]);

    DiscountAllocation::query()->create([
        'invoice_discount_id' => $invoiceDiscount->id,
        'invoice_line_id' => $line->id,
        'amount' => 2_000_000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    $creditEntitlement = FinanceCreditEntitlement::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'finance',
        'source_kind' => 'defer_settlement',
        'source_ref' => 'defer-case:summary-1',
        'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED,
        'amount' => 3_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['semester_id' => $this->semester->id],
        'approved_at' => now(),
    ]);

    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $creditEntitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => 3_000_000,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);

    expect(
        FinanceCharge::query()->where('student_id', $this->student->id)->where('amount', '<', 0)->count()
    )->toBe(0);

    $summary = studentChargesSummary($this->student, $this->semester->id);

    expect($summary['total_charges'])->toBe(10_000_000.0)
        ->and($summary['total_credits'])->toBe(5_000_000.0)
        ->and($summary['net_amount'])->toBe(5_000_000.0);
});

it('does not count legacy negative charge lines after wave-7 backstop retirement', function (): void {
    seedPositiveTuitionCharge($this->student, $this->semester, 10_000_000);

    $invoice = StudentInvoice::query()->where('student_id', $this->student->id)->firstOrFail();

    $creditCharge = FinanceCharge::query()->create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_DEFER_CREDIT,
        'amount' => -1_500_000,
        'description' => 'Legacy defer credit',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $creditCharge->id,
        'amount_snapshot' => -1_500_000,
        'description_snapshot' => 'Legacy defer credit',
        'status' => 'active',
    ]);

    $summary = studentChargesSummary($this->student, $this->semester->id);

    // Wave 7: reductions require discount_allocations / credit_applications.
    expect($summary['total_charges'])->toBe(10_000_000.0)
        ->and($summary['total_credits'])->toBe(0.0)
        ->and($summary['net_amount'])->toBe(10_000_000.0);
});

it('counts full credit applications even when payments would clamp settlement residual credit', function (): void {
    [, $line] = seedPositiveTuitionCharge($this->student, $this->semester, 10_000_000);

    $billingAccount = BillingAccount::query()->firstOrCreate(
        ['student_id' => $this->student->id],
    );

    $creditEntitlement = FinanceCreditEntitlement::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'finance',
        'source_kind' => 'defer_settlement',
        'source_ref' => 'defer-case:summary-paid',
        'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED,
        'amount' => 3_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['semester_id' => $this->semester->id],
        'approved_at' => now(),
    ]);

    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $creditEntitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => 3_000_000,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);

    $payment = Payment::query()->create([
        'student_id' => $this->student->id,
        'amount' => 8_000_000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 8_000_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $summary = studentChargesSummary($this->student, $this->semester->id);

    // Settlement residual credit would clamp to 2M (net 10M - paid 8M), but charges
    // summary must report the full 3M credit application as giảm trừ.
    expect($summary['total_charges'])->toBe(10_000_000.0)
        ->and($summary['total_credits'])->toBe(3_000_000.0)
        ->and($summary['net_amount'])->toBe(7_000_000.0);
});

it('does not double-count when both discount allocation and legacy negative line exist on same invoice', function (): void {
    [$invoice, $line] = seedPositiveTuitionCharge($this->student, $this->semester, 10_000_000);

    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'legacy',
        'description' => 'Voucher',
        'amount' => 2_000_000,
        'reference_id' => 9,
        'status' => 'active',
    ]);

    DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 2_000_000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    // Dual-carrier legacy row (should not inflate total_credits beyond 2M).
    $negative = FinanceCharge::query()->create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_VOUCHER_CREDIT,
        'amount' => -2_000_000,
        'description' => 'Legacy voucher negative',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $negative->id,
        'amount_snapshot' => -2_000_000,
        'description_snapshot' => 'Legacy voucher negative',
        'status' => 'active',
    ]);

    $summary = studentChargesSummary($this->student, $this->semester->id);

    expect($summary['total_credits'])->toBe(2_000_000.0)
        ->and($summary['net_amount'])->toBe(8_000_000.0);
});
