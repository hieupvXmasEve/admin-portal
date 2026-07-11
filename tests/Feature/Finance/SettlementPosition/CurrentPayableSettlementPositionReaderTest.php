<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
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
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Shared\Contracts\Finance\SettlementPositionReader;
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

function createPayableSettlementLine(string $amount = '1000000.00'): array
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:position-reader:'.uniqid('', true),
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
        'description' => 'Retake fee for settlement position reader',
        'created_by_user_id' => test()->user->id,
    ]);

    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    return [$obligation, $line, $line->invoice()->firstOrFail()];
}

function applyPositionCash(InvoiceLine $line, string $amount): void
{
    $payment = Payment::query()->create([
        'student_id' => test()->student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_CASH,
        'source' => 'settlement-position-test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
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
}

function applyPositionDiscount(StudentInvoice $invoice, InvoiceLine $line, string $amount): void
{
    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'settlement-position-test',
        'description' => 'Settlement position test discount',
        'amount' => $amount,
        'status' => 'active',
        'approved_by' => test()->user->id,
    ]);

    DiscountAllocation::query()->create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'allocation',
        'allocation_rule' => 'settlement_position_test',
    ]);
}

function applyPositionCredit(InvoiceLine $line, string $amount): void
{
    $entitlement = FinanceCreditEntitlement::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'settlement_position_test',
        'source_ref' => 'credit:'.uniqid('', true),
        'entitlement_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'settlement_position_test',
        'pricing_snapshot' => [],
        'approved_at' => now(),
    ]);

    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $entitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
        'created_by' => test()->user->id,
    ]);
}

it('returns a valid VND position with cash, discount, and credit kept separate', function (): void {
    [$obligation, $line, $invoice] = createPayableSettlementLine();
    applyPositionDiscount($invoice, $line, '200000.00');
    applyPositionCash($line, '300000.00');
    applyPositionCredit($line, '100000.00');

    $position = app(SettlementPositionReader::class)->forPayableLine((int) $line->id);
    $obligationPosition = app(SettlementPositionReader::class)->forFinanceObligation((int) $obligation->id);

    expect($position->scope_type)->toBe(SettlementPosition::SCOPE_PAYABLE_LINE)
        ->and($position->scope_id)->toBe((int) $line->id)
        ->and($position->finance_obligation_id)->toBe((int) $obligation->id)
        ->and($position->position_mode)->toBe(SettlementPosition::MODE_CURRENT)
        ->and($position->captured_at)->not->toBeNull()
        ->and($position->isValid())->toBeTrue()
        ->and($position->settlement_state)->toBe(SettlementPosition::STATE_PARTIALLY_SETTLED)
        ->and($position->amounts)->not->toBeNull()
        ->and($position->amounts->gross->amount)->toBe('1000000.00')
        ->and($position->amounts->discount->amount)->toBe('200000.00')
        ->and($position->amounts->cash->amount)->toBe('300000.00')
        ->and($position->amounts->credit->amount)->toBe('100000.00')
        ->and($position->amounts->remaining->amount)->toBe('400000.00')
        ->and($position->amounts->cash->currency)->toBe('VND')
        ->and($position->amounts->cash->scale)->toBe(2)
        ->and($position->issues)->toBe([]);

    expect($obligationPosition->scope_type)->toBe(SettlementPosition::SCOPE_FINANCE_OBLIGATION)
        ->and($obligationPosition->scope_id)->toBe((int) $obligation->id)
        ->and($obligationPosition->payable_line_id)->toBe((int) $line->id)
        ->and($obligationPosition->isValid())->toBeTrue()
        ->and($obligationPosition->amounts->remaining->amount)->toBe('400000.00');
});

it('does not treat a missing payable line or finance obligation as settled', function (): void {
    $reader = app(SettlementPositionReader::class);

    $missingLine = $reader->forPayableLine(999_999);
    $missingObligation = $reader->forFinanceObligation(999_999);

    expect($missingLine->isValid())->toBeFalse()
        ->and($missingLine->settlement_state)->toBe(SettlementPosition::STATE_MISSING)
        ->and($missingLine->issues[0]->code)->toBe(SettlementPositionIssue::MISSING_PAYABLE_LINE)
        ->and($missingObligation->isValid())->toBeFalse()
        ->and($missingObligation->settlement_state)->toBe(SettlementPosition::STATE_MISSING)
        ->and($missingObligation->issues[0]->code)->toBe(SettlementPositionIssue::MISSING_FINANCE_OBLIGATION);
});

it('does not treat an obligation without a current payable line as settled', function (): void {
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'retake:without-payable-line',
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => '1000000.00',
        'currency' => 'VND',
        'pricing_rule_version' => 'retake_fee:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'retake_fee:test'],
        'accepted_at' => now(),
    ]);

    $position = app(SettlementPositionReader::class)->forFinanceObligation((int) $obligation->id);

    expect($position->isValid())->toBeFalse()
        ->and($position->settlement_state)->toBe(SettlementPosition::STATE_MISSING)
        ->and($position->issues[0]->code)->toBe(SettlementPositionIssue::MISSING_PAYABLE_LINE);
});

it('fails closed when a payable line has no Finance-owned currency source', function (): void {
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-MISSING-CURRENCY',
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => '1000000.00',
        'description' => 'Legacy charge without obligation currency',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => '1000000.00',
        'description_snapshot' => 'Legacy charge without obligation currency',
        'status' => 'active',
    ]);

    $position = app(SettlementPositionReader::class)->forPayableLine((int) $line->id);

    expect($position->isValid())->toBeFalse()
        ->and($position->hasIssue(SettlementPositionIssue::MISSING_CURRENCY))->toBeTrue();
});

it('preserves raw overpayment evidence and invalidates instead of the legacy clamp', function (): void {
    [, $line, $invoice] = createPayableSettlementLine();
    applyPositionCash($line, '1200000.00');

    $position = app(SettlementPositionReader::class)->forPayableLine((int) $line->id);
    $legacySnapshot = app(SettlementService::class)->deriveInvoiceSnapshot($invoice->fresh());

    expect($legacySnapshot['paid'])->toBe(1_000_000.0)
        ->and($legacySnapshot['remaining'])->toBe(0.0)
        ->and($position->isValid())->toBeFalse()
        ->and($position->amounts)->toBeNull()
        ->and($position->raw_evidence->cash->amount)->toBe('1200000.00')
        ->and($position->raw_evidence->remaining->amount)->toBe('-200000.00')
        ->and($position->hasIssue(SettlementPositionIssue::CASH_EXCEEDS_NET_DUE))->toBeTrue()
        ->and($position->hasIssue(SettlementPositionIssue::NEGATIVE_RAW_REMAINING))->toBeTrue();
});

it('preserves raw over-discount and over-credit evidence as blocking issues', function (): void {
    [, $discountLine, $discountInvoice] = createPayableSettlementLine();
    applyPositionDiscount($discountInvoice, $discountLine, '1200000.00');

    $discountPosition = app(SettlementPositionReader::class)->forPayableLine((int) $discountLine->id);
    $legacyDiscountSnapshot = app(SettlementService::class)->deriveInvoiceSnapshot($discountInvoice->fresh());

    expect($legacyDiscountSnapshot['net'])->toBe(0.0)
        ->and($legacyDiscountSnapshot['remaining'])->toBe(0.0)
        ->and($discountPosition->isValid())->toBeFalse()
        ->and($discountPosition->raw_evidence->discount->amount)->toBe('1200000.00')
        ->and($discountPosition->raw_evidence->remaining->amount)->toBe('-200000.00')
        ->and($discountPosition->hasIssue(SettlementPositionIssue::DISCOUNT_EXCEEDS_GROSS))->toBeTrue();

    $discountInvoice->update(['status' => 'paid']);

    [, $creditLine, $creditInvoice] = createPayableSettlementLine('2000000.00');
    applyPositionCredit($creditLine, '2200000.00');

    $creditPosition = app(SettlementPositionReader::class)->forPayableLine((int) $creditLine->id);
    $legacyCreditSnapshot = app(SettlementService::class)->deriveInvoiceSnapshot($creditInvoice->fresh());

    expect($legacyCreditSnapshot['credit'])->toBe(2_000_000.0)
        ->and($legacyCreditSnapshot['remaining'])->toBe(0.0)
        ->and($creditPosition->isValid())->toBeFalse()
        ->and($creditPosition->raw_evidence->credit->amount)->toBe('2200000.00')
        ->and($creditPosition->raw_evidence->remaining->amount)->toBe('-200000.00')
        ->and($creditPosition->hasIssue(SettlementPositionIssue::CREDIT_EXCEEDS_REMAINING))->toBeTrue();
});
