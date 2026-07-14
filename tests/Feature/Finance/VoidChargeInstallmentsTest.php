<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-12: voiding a charge must leave no live installments, must not silently
 * auto-reallocate when the caller opts out, and a cancelled installment must
 * never be resurrected by a late webhook.
 */
function makeVoidScenario(float $gross = 20_000_000): array
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
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => $gross,
        'description' => 'Retake',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-VOID-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $gross,
        'description_snapshot' => 'Retake line',
        'status' => 'active',
    ]);

    return [$student, $semester, $charge, $invoice];
}

it('cancels pending and awaiting_payment installments when a charge is voided', function () {
    [$student, , $charge] = makeVoidScenario();

    FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 2,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(60)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_PENDING,
    ]);

    $result = app(VoidFinanceChargeAction::class)->handle($charge->id, 'Void with plan');

    $live = FinanceChargeInstallment::query()
        ->where('finance_charge_id', $charge->id)
        ->whereIn('status', [
            FinanceChargeInstallment::STATUS_PENDING,
            FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        ])
        ->count();

    expect($result['cancelled_installments'])->toBe(2)
        ->and($live)->toBe(0)
        ->and(FinanceChargeInstallment::where('finance_charge_id', $charge->id)
            ->where('status', FinanceChargeInstallment::STATUS_CANCELLED)->count())->toBe(2);
});

it('does not auto-reallocate released payments when the caller opts out', function () {
    [$student, , $charge, $invoice] = makeVoidScenario(15_000_000);

    $line = InvoiceLine::where('charge_id', $charge->id)->firstOrFail();
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 15_000_000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $line, 15_000_000, 'application');

    $result = app(VoidFinanceChargeAction::class)->handle($charge->id, 'Void inert', null, false);

    expect($result['released_amount'])->toBe(15_000_000.0)
        ->and($result['reallocated_allocations'])->toBe(0)
        ->and($result['reallocated_amount'])->toBe(0.0);
});

it('blocks void when an awaiting_payment installment is linked to a live DNG request', function () {
    [$student, , $charge] = makeVoidScenario();

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'S1',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 10_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG, // live at provider
    ]);

    $installment = FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dng->id,
    ]);

    // Direct void must be blocked — operator has to cancel the live DNG first.
    expect(fn () => app(VoidFinanceChargeAction::class)->handle($charge->id, 'Void before payment'))
        ->toThrow(RuntimeException::class);

    // Nothing changed: charge still active, installment still awaiting.
    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
});

it('does not resurrect a cancelled installment from a late DNG webhook', function () {
    [$student, , $charge] = makeVoidScenario();

    // DNG already cancelled (terminal) — e.g. the operator cancelled it before
    // voiding the charge, so the void is allowed to cancel the installment.
    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => 'S1',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 10_000_000,
        'status' => DngPaymentRequest::STATUS_CANCELLED,
        'paid_at' => now(),
    ]);

    $installment = FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dng->id,
    ]);

    // Void cancels the installment (DNG already terminal → not blocked).
    app(VoidFinanceChargeAction::class)->handle($charge->id, 'Void before payment');
    expect($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_CANCELLED);

    // A late webhook for the same DNG must not flip it back to paid.
    $settled = app(SettleInstallmentFromDngAction::class)->handle($dng->fresh());

    expect($settled)->toHaveCount(0)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_CANCELLED);
});
