<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\SplitChargeIntoInstallmentsAction;
use App\Modules\Finance\Exceptions\ChargeHasPaidInstallmentException;
use App\Modules\Finance\Exceptions\InstallmentSplitNotAllowedException;
use App\Modules\Finance\Exceptions\InvalidInstallmentPlanException;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: create a FinanceCharge with given gross amount and optional
 * scholarship discount allocated via a real invoice line (so the
 * `discount_amount` accessor reflects realistic data).
 */
function createSplitTestCharge(float $gross, float $discount = 0.0): FinanceCharge
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $gross,
        'description' => 'Test HP',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    if ($discount > 0) {
        $invoice = StudentInvoice::create([
            'invoice_number' => 'TEST-INV-'.uniqid(),
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'pending',
            'total_amount' => $gross,
            'paid_amount' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $gross,
            'description_snapshot' => 'Test HP line',
            'status' => 'active',
        ]);

        $invDiscount = InvoiceDiscount::create([
            'invoice_id' => $invoice->id,
            'discount_type' => 'scholarship',
            'discount_source' => 'test_scholarship',
            'description' => 'Test scholarship',
            'amount' => $discount,
        ]);

        DiscountAllocation::create([
            'invoice_discount_id' => $invDiscount->id,
            'invoice_line_id' => $line->id,
            'amount' => $discount,
            'entry_type' => 'allocation',
            'allocation_rule' => 'test_full',
        ]);
    }

    return $charge->fresh();
}

beforeEach(function () {
    $this->action = app(SplitChargeIntoInstallmentsAction::class);
});

// =========================================================================
// Case 1: sum amount mismatch → throw InvalidInstallmentPlanException
// =========================================================================
it('throws when sum of installment amounts does not equal net split target', function () {
    $charge = createSplitTestCharge(gross: 15_000_000);

    $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_000_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 7_000_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);
})->throws(InvalidInstallmentPlanException::class, 'installment_sum_mismatch');

// =========================================================================
// Case 2: installment_no not sequential → throw
// =========================================================================
it('throws when installment_no is not contiguous from 1', function () {
    $charge = createSplitTestCharge(gross: 15_000_000);

    $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_500_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 3, 'amount' => 7_500_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);
})->throws(InvalidInstallmentPlanException::class);

// =========================================================================
// Case 3: due_date not ascending → throw
// =========================================================================
it('throws when due_date is not ascending by installment_no', function () {
    $charge = createSplitTestCharge(gross: 15_000_000);

    $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_500_000, 'due_date' => now()->addDays(60)->toDateString()],
        ['installment_no' => 2, 'amount' => 7_500_000, 'due_date' => now()->addDays(30)->toDateString()],
    ]);
})->throws(InvalidInstallmentPlanException::class);

// =========================================================================
// Case 4: charge already has paid installment → throw
// =========================================================================
it('throws when charge has at least one paid installment (Phase 1 lock)', function () {
    $charge = createSplitTestCharge(gross: 15_000_000);

    FinanceChargeInstallment::factory()->paid()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 15_000_000,
    ]);

    $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_500_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 7_500_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);
})->throws(ChargeHasPaidInstallmentException::class);

// =========================================================================
// Case 5: split succeeds on clean charge → N rows inserted with status pending
// =========================================================================
it('inserts N installments with status pending on clean charge', function () {
    $charge = createSplitTestCharge(gross: 15_000_000);

    $result = $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_500_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 7_500_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    expect($result)->toHaveCount(2);
    expect($result->pluck('status')->all())->toBe(['pending', 'pending']);
    expect($result->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([7_500_000.0, 7_500_000.0]);
    expect($result->pluck('installment_no')->all())->toBe([1, 2]);
});

// =========================================================================
// Case 6: re-split when no paid → old pending rows wiped, new inserted
// =========================================================================
it('replaces existing pending plan when no paid installment exists', function () {
    $charge = createSplitTestCharge(gross: 15_000_000);

    // Seed initial plan
    $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 5_000_000, 'due_date' => now()->addDays(15)->toDateString()],
        ['installment_no' => 2, 'amount' => 5_000_000, 'due_date' => now()->addDays(45)->toDateString()],
        ['installment_no' => 3, 'amount' => 5_000_000, 'due_date' => now()->addDays(75)->toDateString()],
    ]);
    expect($charge->installments()->count())->toBe(3);

    // Re-split to 2x50%
    $result = $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_500_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 7_500_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    expect($result)->toHaveCount(2);
    expect($charge->installments()->count())->toBe(2);
});

// =========================================================================
// Case 7: NET split target — charge 20M with 5M scholarship → split 15M
// =========================================================================
it('uses NET split target (charge.amount - discount) not gross', function () {
    $charge = createSplitTestCharge(gross: 20_000_000, discount: 5_000_000);

    // Sanity check: discount accessor reads the InvoiceDiscount row.
    expect((float) $charge->discount_amount)->toBe(5_000_000.0);

    // Splitting at gross (10M each) must fail — sum 20M != net target 15M
    expect(fn () => $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 10_000_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 10_000_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]))->toThrow(InvalidInstallmentPlanException::class, 'installment_sum_mismatch');

    // Splitting at net (7.5M each) must succeed
    $result = $this->action->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 7_500_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 7_500_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    expect($result)->toHaveCount(2);
    expect($result->sum(fn ($i) => (float) $i->amount))->toBe(15_000_000.0);
});

// =========================================================================
// Case 8 (bonus): credit charge cannot be split
// =========================================================================
it('throws when trying to split a credit charge (amount <= 0)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);
    $credit = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceEntitlementType::ScholarshipCredit,
        'amount' => -5_000_000,
        'description' => 'Scholarship',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $this->action->handle($credit->id, [
        ['installment_no' => 1, 'amount' => 100, 'due_date' => now()->addDays(30)->toDateString()],
    ]);
})->throws(InstallmentSplitNotAllowedException::class, 'charge_is_credit');
