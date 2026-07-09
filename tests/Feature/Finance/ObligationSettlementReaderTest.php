<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);
});

function createSettlementObligation(float $amount = 1_000_000): array
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:reader-test',
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'retake_fee:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'retake_fee:test'],
        'accepted_at' => now(),
    ]);

    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => $amount,
        'description' => 'Retake fee for settlement reader',
        'created_by_user_id' => test()->user->id,
    ]);

    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();
    $invoice = $line->invoice()->firstOrFail();

    return [$obligation, $charge, $line, $invoice];
}

function readSettlement(string $sourceRef = 'retake:reader-test'): ObligationSettlementResult
{
    return app(ObligationSettlementReader::class)->getSettlement(
        sourceSystem: 'academic',
        sourceKind: 'course_retake_registration',
        sourceRef: $sourceRef,
        obligationType: FinanceCharge::TYPE_RETAKE_FEE,
    );
}

function applySettlementPayment(InvoiceLine $line, float $amount, string $status = Payment::STATUS_COMPLETED): Payment
{
    $payment = Payment::query()->create([
        'student_id' => test()->student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_CASH,
        'source' => 'reader-test',
        'paid_at' => now(),
        'status' => $status,
        'received_by_user_id' => test()->user->id,
    ]);

    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'application',
        'applied_at' => now(),
        'created_by' => test()->user->id,
    ]);

    return $payment;
}

function applySettlementDiscount(StudentInvoice $invoice, InvoiceLine $line, float $amount, string $status = 'active'): void
{
    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'reader-test',
        'description' => 'Reader test discount',
        'amount' => $amount,
        'status' => $status,
        'approved_by' => test()->user->id,
    ]);

    DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'allocation',
        'allocation_rule' => 'reader_test',
    ]);
}

it('returns missing_finance_obligation for absent source instead of cleared', function (): void {
    $settlement = readSettlement('retake:missing');

    expect($settlement->settlement_state)->toBe(ObligationSettlementResult::STATE_MISSING_FINANCE_OBLIGATION)
        ->and($settlement->isSettled())->toBeFalse()
        ->and(app(ObligationSettlementReader::class)->isSettled(
            sourceSystem: 'academic',
            sourceKind: 'course_retake_registration',
            sourceRef: 'retake:missing',
            obligationType: FinanceCharge::TYPE_RETAKE_FEE,
        ))->toBeFalse()
        ->and($settlement->payable)->toBe(0.0)
        ->and($settlement->paid)->toBe(0.0)
        ->and($settlement->discount)->toBe(0.0)
        ->and($settlement->outstanding)->toBe(0.0);
});

it('derives unpaid settlement from active invoice lines and ignores stale invoice cache', function (): void {
    [, , , $invoice] = createSettlementObligation(1_000_000);

    $invoice->forceFill([
        'cached_total_amount' => 0,
        'cached_paid_amount' => 1_000_000,
        'status' => 'paid',
    ])->save();

    $settlement = readSettlement();

    expect($settlement->settlement_state)->toBe(ObligationSettlementResult::STATE_UNPAID)
        ->and($settlement->isSettled())->toBeFalse()
        ->and($settlement->payable)->toBe(1_000_000.0)
        ->and($settlement->paid)->toBe(0.0)
        ->and($settlement->discount)->toBe(0.0)
        ->and($settlement->outstanding)->toBe(1_000_000.0);
});

it('derives partial and paid settlement from completed payment applications', function (): void {
    [, , $line] = createSettlementObligation(1_000_000);

    applySettlementPayment($line, 400_000);

    $partial = readSettlement();

    expect($partial->settlement_state)->toBe(ObligationSettlementResult::STATE_PARTIALLY_PAID)
        ->and($partial->isSettled())->toBeFalse()
        ->and($partial->paid)->toBe(400_000.0)
        ->and($partial->outstanding)->toBe(600_000.0);

    applySettlementPayment($line, 600_000);

    $paid = readSettlement();

    expect($paid->settlement_state)->toBe(ObligationSettlementResult::STATE_PAID)
        ->and($paid->isSettled())->toBeTrue()
        ->and($paid->paid)->toBe(1_000_000.0)
        ->and($paid->outstanding)->toBe(0.0);
});

it('ignores applications backed only by pending payments', function (): void {
    [, , $line] = createSettlementObligation(1_000_000);

    applySettlementPayment($line, 1_000_000, Payment::STATUS_PENDING);

    $settlement = readSettlement();

    expect($settlement->settlement_state)->toBe(ObligationSettlementResult::STATE_UNPAID)
        ->and($settlement->paid)->toBe(0.0)
        ->and($settlement->outstanding)->toBe(1_000_000.0);
});

it('derives overpaid settlement when completed applications exceed net payable', function (): void {
    [, , $line] = createSettlementObligation(1_000_000);

    applySettlementPayment($line, 1_200_000);

    $settlement = readSettlement();

    expect($settlement->settlement_state)->toBe(ObligationSettlementResult::STATE_OVERPAID)
        ->and($settlement->isSettled())->toBeTrue()
        ->and($settlement->paid)->toBe(1_200_000.0)
        ->and($settlement->outstanding)->toBe(0.0);
});

it('derives settled_by_discount_or_credit when allocations cover the payable', function (): void {
    [, , $line, $invoice] = createSettlementObligation(1_000_000);

    applySettlementDiscount($invoice, $line, 1_000_000);

    $settlement = readSettlement();

    expect($settlement->settlement_state)->toBe(ObligationSettlementResult::STATE_SETTLED_BY_DISCOUNT_OR_CREDIT)
        ->and($settlement->isSettled())->toBeTrue()
        ->and($settlement->payable)->toBe(1_000_000.0)
        ->and($settlement->discount)->toBe(1_000_000.0)
        ->and($settlement->outstanding)->toBe(0.0);
});

it('ignores allocations whose discount header is reversed', function (): void {
    [, , $line, $invoice] = createSettlementObligation(1_000_000);

    applySettlementDiscount($invoice, $line, 1_000_000, 'reversed');

    $settlement = readSettlement();

    expect($settlement->settlement_state)->toBe(ObligationSettlementResult::STATE_UNPAID)
        ->and($settlement->discount)->toBe(0.0)
        ->and($settlement->outstanding)->toBe(1_000_000.0);
});
