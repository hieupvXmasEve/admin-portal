<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Actions\SplitChargeIntoInstallmentsAction;
use App\Modules\Finance\Exceptions\InstallmentReconciliationException;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-09: when a discount changes net due AFTER a charge is split into
 * installments, the pending installments must be recomputed so they still sum to
 * the new net due (or surface for review when committed rows already exceed it).
 */
function makeReconcileCharge(float $gross): array
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
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-REC-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $gross,
        'description_snapshot' => 'Tuition line',
        'status' => 'active',
    ]);

    return [$charge, $invoice];
}

it('recomputes pending installments proportionally when a discount lowers net due', function () {
    [$charge, $invoice] = makeReconcileCharge(20_000_000);

    // Split into 2 equal pending installments at full gross (no discount yet).
    app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 10_000_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 10_000_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    // Apply a 25% scholarship AFTER the split → net due drops to 15,000,000.
    app(InvoiceGenerationService::class)->applyInvoiceDiscount(
        $invoice,
        'scholarship',
        5_000_000,
        StudentScholarshipAward::class,
        'Scholarship',
        1,
    );

    $installments = FinanceChargeInstallment::query()
        ->where('finance_charge_id', $charge->id)
        ->where('status', FinanceChargeInstallment::STATUS_PENDING)
        ->orderBy('installment_no')
        ->get();

    expect($installments)->toHaveCount(2)
        ->and((float) $installments->sum('amount'))->toBe(15_000_000.0)
        ->and((float) $installments[0]->amount)->toBe(7_500_000.0)
        ->and((float) $installments[1]->amount)->toBe(7_500_000.0);
});

it('keeps the rounding remainder so reconciled installments sum exactly', function () {
    [$charge, $invoice] = makeReconcileCharge(10_000_000);

    app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 3_333_333, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 3_333_333, 'due_date' => now()->addDays(60)->toDateString()],
        ['installment_no' => 3, 'amount' => 3_333_334, 'due_date' => now()->addDays(90)->toDateString()],
    ]);

    // Discount of 1 → net due 9,999,999 spread across 3 rows must sum exactly.
    app(InvoiceGenerationService::class)->applyInvoiceDiscount(
        $invoice,
        'scholarship',
        1,
        StudentScholarshipAward::class,
        'Tiny scholarship',
        1,
    );

    $sum = (float) FinanceChargeInstallment::query()
        ->where('finance_charge_id', $charge->id)
        ->where('status', FinanceChargeInstallment::STATUS_PENDING)
        ->sum('amount');

    expect($sum)->toBe(9_999_999.0);
});

it('blocks reconciliation when committed installments already exceed the new net due', function () {
    [$charge, $invoice] = makeReconcileCharge(20_000_000);

    app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 10_000_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 10_000_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    // First installment is already pushed to DNG (committed).
    FinanceChargeInstallment::query()
        ->where('finance_charge_id', $charge->id)
        ->where('installment_no', 1)
        ->update(['status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT]);

    // A discount that drops net due to 8,000,000 (< committed 10,000,000) must block.
    expect(fn () => app(InvoiceGenerationService::class)->applyInvoiceDiscount(
        $invoice,
        'scholarship',
        12_000_000,
        StudentScholarshipAward::class,
        'Big scholarship',
        1,
    ))->toThrow(InstallmentReconciliationException::class);

    // The block must be atomic: no discount or allocation may be left behind even
    // though createOrRefreshInvoiceDiscount ran before the reconcile threw.
    expect(InvoiceDiscount::where('invoice_id', $invoice->id)->where('discount_type', 'scholarship')->count())->toBe(0)
        ->and(DiscountAllocation::query()
            ->whereIn('invoice_line_id', InvoiceLine::where('charge_id', $charge->id)->pluck('id'))
            ->count())->toBe(0);
});
