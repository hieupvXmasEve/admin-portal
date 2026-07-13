<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    $this->billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $this->student->id]);
    $this->invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-SP-'.uniqid(),
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
});

function createAggregatePayableLine(
    StudentInvoice $invoice,
    BillingAccount $billingAccount,
    string $amount = '1000000.00',
    string $feeType = FinanceCharge::TYPE_RETAKE_FEE,
    ?CarbonImmutable $effectiveAt = null,
): InvoiceLine {
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'academic',
        'source_kind' => 'settlement_position_aggregate_test',
        'source_ref' => 'aggregate:'.uniqid('', true),
        'obligation_type' => $feeType,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'settlement_position_aggregate_test',
        'pricing_snapshot' => [],
        'accepted_at' => $effectiveAt ?? now(),
    ]);

    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $invoice->student_id,
        'semester_id' => $invoice->semester_id,
        'charge_type' => $feeType,
        'amount' => $amount,
        'description' => 'Aggregate settlement position test charge',
        'effective_at' => $effectiveAt ?? now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Aggregate settlement position test line',
        'status' => 'active',
    ]);
}

function applyAggregateCash(InvoiceLine $line, string $amount, CarbonImmutable $paidAt): void
{
    $payment = Payment::query()->create([
        'student_id' => $line->invoice()->value('student_id'),
        'amount' => $amount,
        'method' => Payment::METHOD_CASH,
        'source' => 'settlement-position-aggregate-test',
        'paid_at' => $paidAt,
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => test()->user->id,
    ]);

    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'application',
        'applied_at' => $paidAt,
        'created_by' => test()->user->id,
    ]);
}

it('aggregates invoice, fee type, billing account, and exact targets with line breakdowns', function (): void {
    $retakeLine = createAggregatePayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $manualLine = createAggregatePayableLine(
        $this->invoice,
        $this->billingAccount,
        '500000.00',
        FinanceCharge::TYPE_MANUAL_FEE,
    );
    applyAggregateCash($retakeLine, '250000.00', now()->toImmutable());

    $reader = app(SettlementPositionReader::class);
    $invoicePosition = $reader->forInvoice((int) $this->invoice->id);
    $feeTypePosition = $reader->forFeeType((int) $this->billingAccount->id, FinanceCharge::TYPE_RETAKE_FEE);
    $accountPosition = $reader->forBillingAccount((int) $this->billingAccount->id);
    $targetPosition = $reader->forPayableLines([(int) $manualLine->id, (int) $retakeLine->id]);
    $batch = $reader->batch([
        SettlementPositionScope::invoice((int) $this->invoice->id),
        SettlementPositionScope::feeType((int) $this->billingAccount->id, FinanceCharge::TYPE_RETAKE_FEE),
        SettlementPositionScope::billingAccount((int) $this->billingAccount->id),
        SettlementPositionScope::payableLines([(int) $manualLine->id, (int) $retakeLine->id]),
    ]);

    expect($invoicePosition->isValid())->toBeTrue()
        ->and($invoicePosition->scope_type)->toBe(SettlementPosition::SCOPE_INVOICE)
        ->and($invoicePosition->billing_account_id)->toBe((int) $this->billingAccount->id)
        ->and($invoicePosition->amounts->gross->amount)->toBe('1500000.00')
        ->and($invoicePosition->amounts->cash->amount)->toBe('250000.00')
        ->and($invoicePosition->amounts->remaining->amount)->toBe('1250000.00')
        ->and($invoicePosition->payable_line_breakdown)->toHaveCount(2)
        ->and($feeTypePosition->amounts->gross->amount)->toBe('1000000.00')
        ->and($feeTypePosition->fee_type)->toBe(FinanceCharge::TYPE_RETAKE_FEE)
        ->and($accountPosition->amounts->gross->amount)->toBe('1500000.00')
        ->and($targetPosition->amounts->remaining->amount)->toBe('1250000.00')
        ->and($batch[0]->amounts->remaining->amount)->toBe($invoicePosition->amounts->remaining->amount)
        ->and($batch[1]->amounts->remaining->amount)->toBe($feeTypePosition->amounts->remaining->amount)
        ->and($batch[2]->amounts->remaining->amount)->toBe($accountPosition->amounts->remaining->amount)
        ->and($batch[3]->amounts->remaining->amount)->toBe($targetPosition->amounts->remaining->amount)
        ->and($batch[1]->captured_at->equalTo($batch[0]->captured_at))->toBeTrue()
        ->and($batch[1]->snapshot_version)->toBe($batch[0]->snapshot_version)
        ->and($batch[2]->snapshot_version)->toBe($batch[0]->snapshot_version)
        ->and($batch[3]->snapshot_version)->toBe($batch[0]->snapshot_version);
});

it('invalidates an exact requested scope when any requested target is invalid', function (): void {
    $validLine = createAggregatePayableLine($this->invoice, $this->billingAccount);
    $invalidLine = createAggregatePayableLine($this->invoice, $this->billingAccount, '0.00');

    $position = app(SettlementPositionReader::class)->forPayableLines([
        (int) $validLine->id,
        (int) $invalidLine->id,
    ]);

    expect($position->isValid())->toBeFalse()
        ->and($position->amounts)->toBeNull()
        ->and($position->payable_line_breakdown)->toHaveCount(2)
        ->and($position->hasIssue(SettlementPositionIssue::PAYABLE_LINE_NOT_COLLECTIBLE))->toBeTrue();
});

it('excludes void history from current business scopes but keeps exact target validation fail closed', function (): void {
    $activeLine = createAggregatePayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $voidLine = createAggregatePayableLine($this->invoice, $this->billingAccount, '500000.00');
    $voidedAt = now();

    $voidLine->update([
        'status' => 'void',
        'voided_at' => $voidedAt,
        'void_reason' => 'Historical line excluded from current business scope',
    ]);
    $voidLine->charge()->update([
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => $voidedAt,
        'void_reason' => 'Historical charge excluded from current business scope',
    ]);

    $reader = app(SettlementPositionReader::class);
    $invoicePosition = $reader->forInvoice((int) $this->invoice->id);
    $accountPosition = $reader->forBillingAccount((int) $this->billingAccount->id);
    $exactPosition = $reader->forPayableLines([(int) $activeLine->id, (int) $voidLine->id]);

    expect($invoicePosition->isValid())->toBeTrue()
        ->and($invoicePosition->amounts->gross->amount)->toBe('1000000.00')
        ->and($invoicePosition->payable_line_breakdown)->toHaveCount(1)
        ->and($accountPosition->isValid())->toBeTrue()
        ->and($accountPosition->amounts->gross->amount)->toBe('1000000.00')
        ->and($accountPosition->payable_line_breakdown)->toHaveCount(1)
        ->and($exactPosition->isValid())->toBeFalse()
        ->and($exactPosition->hasIssue(SettlementPositionIssue::PAYABLE_LINE_NOT_ACTIVE))->toBeTrue();
});

it('does not rewrite an as-of position with payment evidence effective after the requested timestamp', function (): void {
    $snapshotAt = CarbonImmutable::parse('2026-07-01 12:00:00');
    $line = createAggregatePayableLine(
        $this->invoice,
        $this->billingAccount,
        '1000000.00',
        FinanceCharge::TYPE_RETAKE_FEE,
        $snapshotAt->subDay(),
    );
    applyAggregateCash($line, '300000.00', $snapshotAt->addDay());

    $reader = app(SettlementPositionReader::class);
    $asOfPosition = $reader->forPayableLine((int) $line->id, $snapshotAt);
    $currentPosition = $reader->forPayableLine((int) $line->id);

    expect($asOfPosition->isValid())->toBeTrue()
        ->and($asOfPosition->position_mode)->toBe(SettlementPosition::MODE_AS_OF)
        ->and($asOfPosition->captured_at->equalTo($snapshotAt))->toBeTrue()
        ->and($asOfPosition->amounts->cash->amount)->toBe('0.00')
        ->and($asOfPosition->amounts->remaining->amount)->toBe('1000000.00')
        ->and($currentPosition->amounts->cash->amount)->toBe('300000.00')
        ->and($currentPosition->amounts->remaining->amount)->toBe('700000.00');
});

it('preserves as-of cash evidence and returns an issue when later payment status has no business timestamp', function (): void {
    $snapshotAt = CarbonImmutable::parse('2026-07-01 12:00:00');
    $line = createAggregatePayableLine(
        $this->invoice,
        $this->billingAccount,
        '1000000.00',
        FinanceCharge::TYPE_RETAKE_FEE,
        $snapshotAt->subDay(),
    );
    applyAggregateCash($line, '300000.00', $snapshotAt->subHour());
    Payment::query()->latest('id')->firstOrFail()->update(['status' => Payment::STATUS_REFUNDED]);

    $position = app(SettlementPositionReader::class)->forPayableLine((int) $line->id, $snapshotAt);

    expect($position->isValid())->toBeFalse()
        ->and($position->raw_evidence->cash->amount)->toBe('300000.00')
        ->and($position->hasIssue(SettlementPositionIssue::AS_OF_UNRELIABLE_PAYMENT_STATUS))->toBeTrue();
});

it('returns an as-of integrity issue rather than guessing discount chronology without a business timestamp', function (): void {
    $line = createAggregatePayableLine($this->invoice, $this->billingAccount);
    $discount = $this->invoice->discounts()->create([
        'discount_type' => 'scholarship',
        'discount_source' => 'settlement-position-aggregate-test',
        'description' => 'Untimestamped allocation',
        'amount' => '100000.00',
        'status' => 'active',
        'approved_by' => $this->user->id,
    ]);
    $discount->allocations()->create([
        'invoice_line_id' => $line->id,
        'amount' => '100000.00',
        'entry_type' => 'allocation',
        'allocation_rule' => 'settlement_position_aggregate_test',
    ]);

    $position = app(SettlementPositionReader::class)->forPayableLine(
        (int) $line->id,
        CarbonImmutable::now()->addDay(),
    );

    expect($position->isValid())->toBeFalse()
        ->and($position->hasIssue(SettlementPositionIssue::AS_OF_UNTIMESTAMPED_DISCOUNT_EVIDENCE))->toBeTrue();
});

it('keeps batch transport parity with single reads without adding queries per target', function (): void {
    $lines = collect(range(1, 4))
        ->map(fn (): InvoiceLine => createAggregatePayableLine($this->invoice, $this->billingAccount))
        ->all();
    $reader = app(SettlementPositionReader::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $oneTarget = $reader->batch([SettlementPositionScope::payableLine((int) $lines[0]->id)]);
    $oneTargetQueryCount = count(DB::getQueryLog());

    DB::flushQueryLog();
    $manyTargets = $reader->batch(array_map(
        fn (InvoiceLine $line): SettlementPositionScope => SettlementPositionScope::payableLine((int) $line->id),
        $lines,
    ));
    $manyTargetQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($manyTargets)->toHaveCount(4)
        ->and($manyTargetQueryCount)->toBe($oneTargetQueryCount)
        ->and($manyTargetQueryCount)->toBeLessThanOrEqual(6);

    foreach ($manyTargets as $index => $position) {
        $singlePosition = $reader->forPayableLine((int) $lines[$index]->id);

        expect($position->amounts->remaining->amount)
            ->toBe($singlePosition->amounts->remaining->amount);
    }
});
