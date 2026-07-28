<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Actions\ReconcileChargeInstallmentsAction;
use App\Modules\Finance\Actions\SplitChargeIntoInstallmentsAction;
use App\Modules\Finance\Exceptions\InstallmentReconciliationException;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
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

    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'installment_reconcile',
        'source_ref' => uniqid('charge:', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $gross,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
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

it('does not subtract a paid installment twice when reconciling against canonical remaining', function (): void {
    [$charge] = makeReconcileCharge(20_000_000);

    app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 10_000_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 10_000_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    // Installment 1 collected: the row is paid AND its cash sits in the ledger,
    // so canonical remaining is already net of it.
    FinanceChargeInstallment::query()
        ->where('finance_charge_id', $charge->id)
        ->where('installment_no', 1)
        ->update(['status' => FinanceChargeInstallment::STATUS_PAID]);

    $payment = Payment::query()->create([
        'student_id' => $charge->student_id,
        'amount' => 12_000_000,
        'method' => Payment::METHOD_GATEWAY,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);

    // 10,000,000 settles installment 1; the extra 2,000,000 lands on the same
    // charge, so only 8,000,000 is still collectible.
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 12_000_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    app(ReconcileChargeInstallmentsAction::class)->handle($charge);

    $rows = FinanceChargeInstallment::query()
        ->where('finance_charge_id', $charge->id)
        ->orderBy('installment_no')
        ->get();

    expect($rows[0]->status)->toBe(FinanceChargeInstallment::STATUS_PAID)
        ->and((float) $rows[0]->amount)->toBe(10_000_000.0)
        ->and((float) $rows[1]->amount)->toBe(8_000_000.0);
});

it('reconciles only pending installments from canonical remaining after credit is applied', function (): void {
    [$charge, $invoice] = makeReconcileCharge(20_000_000);

    app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 10_000_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 10_000_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]);

    $first = FinanceChargeInstallment::query()->where('finance_charge_id', $charge->id)->where('installment_no', 1)->firstOrFail();
    $first->update(['status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT]);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();
    $entitlement = FinanceCreditEntitlement::query()->create([
        'source_system' => 'test', 'source_kind' => 'credit', 'source_ref' => uniqid(),
        'entitlement_type' => FinanceEntitlementType::DeferCredit, 'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED, 'amount' => 5_000_000,
        'currency' => 'VND', 'pricing_rule_version' => 'test', 'pricing_snapshot' => [], 'approved_at' => now(),
    ]);
    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $entitlement->id, 'invoice_line_id' => $line->id,
        'amount' => 5_000_000, 'entry_type' => CreditApplication::ENTRY_APPLICATION, 'applied_at' => now(),
    ]);

    app(ReconcileChargeInstallmentsAction::class)->handle($charge);

    expect((float) $first->fresh()->amount)->toBe(10_000_000.0)
        ->and((float) FinanceChargeInstallment::query()->where('finance_charge_id', $charge->id)->where('installment_no', 2)->value('amount'))->toBe(5_000_000.0);
});
