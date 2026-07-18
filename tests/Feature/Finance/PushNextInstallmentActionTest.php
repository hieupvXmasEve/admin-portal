<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\PushNextInstallmentAction;
use App\Modules\Finance\Actions\ResolveDngReservationOutcomeAction;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Jobs\PushNextInstallmentJob;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Create a charge + student fixture for DNG push tests. Returns the charge
 * (with student preloaded) and the seeded 2-installment plan.
 *
 * @return array{charge: FinanceCharge, installments: Collection<int, FinanceChargeInstallment>}
 */
function createInstallmentPushFixture(): array
{
    $campus = Campus::factory()->withDngMapping('TEST')->create();
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
        'amount' => 15_000_000,
        'description' => 'HP test',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'finance-test',
        'source_kind' => 'installment_push',
        'source_ref' => uniqid('installment:', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 15_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge->update(['finance_obligation_id' => $obligation->id]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-INSTALLMENT-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 15_000_000,
        'description_snapshot' => 'HP test',
        'status' => 'active',
    ]);

    $i2 = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 2,
        'amount' => 7_500_000,
        'due_date' => now()->addDays(60)->toDateString(),
    ]);
    $i1 = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 7_500_000,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    return ['charge' => $charge->fresh(['student']), 'installments' => collect([$i1, $i2])];
}

/**
 * Bind a DngClient mock that returns a fixed success payload from insertNewRecord.
 * Forces DngPaymentService to be re-resolved (it's a singleton holding DngClient).
 */
function mockDngClientPushSuccess(): void
{
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['fake' => 'payload']);
    $mock->shouldReceive('insertNewRecord')->andReturn([
        'data' => [
            'TransactionID' => 'TXN-'.uniqid(),
            'PaymentId' => 'PAY-'.uniqid(),
        ],
    ]);
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);
}

/**
 * Bind a DngClient mock that throws on insertNewRecord (simulates DNG outage).
 */
function mockDngClientPushFail(string $reason = 'DNG HTTP 502'): void
{
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['fake' => 'payload']);
    $mock->shouldReceive('insertNewRecord')->andThrow(new RuntimeException($reason));
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);
}

/**
 * Override the campus code resolver so it doesn't need real campus DNG config.
 */
function mockCampusCodeResolver(string $code = 'TEST'): void
{
    $mock = Mockery::mock(DngCampusCodeResolver::class);
    $mock->shouldReceive('requireForStudent')->andReturn($code);
    app()->instance(DngCampusCodeResolver::class, $mock);
}

beforeEach(function () {
    mockCampusCodeResolver();
});

/**
 * Resolve a fresh PushNextInstallmentAction (after DNG mocks are bound).
 * Avoids the singleton-already-resolved trap for DngPaymentService.
 */
function freshPushAction(): PushNextInstallmentAction
{
    app()->forgetInstance(PushNextInstallmentAction::class);

    return app(PushNextInstallmentAction::class);
}

// =========================================================================
// Case 8: split + push installment 1 → DNG record created, installment linked
// =========================================================================
it('reserves the lowest installment sequence even when row order is reversed', function () {
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();
    $line = InvoiceLine::query()->where('charge_id', $fix['charge']->id)->sole();
    $reader = app(SettlementPositionReader::class);
    $positionBefore = $reader->forPayableLines([(int) $line->id]);
    $billingAccount = BillingAccount::query()->where('student_id', $fix['charge']->student_id)->sole();
    $invoice = StudentInvoice::query()->where('student_id', $fix['charge']->student_id)->sole();
    $invoiceTotalsBefore = [
        'total' => $invoice->total_amount,
        'paid' => $invoice->paid_amount,
        'outstanding' => $invoice->outstanding_balance,
    ];
    $paymentTotalBefore = Payment::query()->where('student_id', $fix['charge']->student_id)->sum('amount');
    $versionBefore = $billingAccount->settlement_version;

    $installment = freshPushAction()->handle($fix['charge']->id);
    $positionAfter = $reader->forPayableLines([(int) $line->id]);

    expect($installment)->not->toBeNull();
    expect($installment->installment_no)->toBe(1);
    expect($installment->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
    expect($installment->dng_payment_request_id)->not->toBeNull();
    expect($installment->last_push_error)->toBeNull();

    $dng = DngPaymentRequest::query()->find($installment->dng_payment_request_id);
    expect($dng)->not->toBeNull();
    expect((float) $dng->amount)->toBe(7_500_000.0);
    expect($dng->chargeLinks->sole()->finance_charge_id)->toBe($fix['charge']->id)
        ->and($dng->chargeLinks->sole()->finance_charge_installment_id)->toBe($installment->id);
    expect($fix['charge']->fresh()->amount)->toBe('15000000.00')
        ->and(InvoiceLine::query()->where('charge_id', $fix['charge']->id)->sole()->amount_snapshot)->toBe('15000000.00')
        ->and($billingAccount->fresh()->settlement_version)->toBe($versionBefore + 3)
        ->and($positionAfter->amounts->gross->amount)->toBe($positionBefore->amounts->gross->amount)
        ->and($positionAfter->amounts->discount->amount)->toBe($positionBefore->amounts->discount->amount)
        ->and($positionAfter->amounts->cash->amount)->toBe($positionBefore->amounts->cash->amount)
        ->and($positionAfter->amounts->credit->amount)->toBe($positionBefore->amounts->credit->amount)
        ->and($positionAfter->amounts->remaining->amount)->toBe($positionBefore->amounts->remaining->amount)
        ->and($invoice->fresh()->total_amount)->toBe($invoiceTotalsBefore['total'])
        ->and($invoice->fresh()->paid_amount)->toBe($invoiceTotalsBefore['paid'])
        ->and($invoice->fresh()->outstanding_balance)->toBe($invoiceTotalsBefore['outstanding'])
        ->and(Payment::query()->where('student_id', $fix['charge']->student_id)->sum('amount'))->toBe($paymentTotalBefore);
});

it('does not push a later installment when the earliest one is already reserved', function () {
    $fix = createInstallmentPushFixture();
    $providerCalls = 0;
    $nestedResult = null;
    $transactionLevelAtProviderCall = null;
    $transactionLevelBeforePush = DB::transactionLevel();
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['fake' => 'payload']);
    $mock->shouldReceive('insertNewRecord')->once()->andReturnUsing(function () use (&$providerCalls, &$nestedResult, &$transactionLevelAtProviderCall, $fix): array {
        $providerCalls++;
        $transactionLevelAtProviderCall = DB::transactionLevel();
        $nestedResult = freshPushAction()->handle($fix['charge']->id);

        return [
            'data' => [
                'TransactionID' => 'TXN-'.uniqid(),
                'PaymentId' => 'PAY-'.uniqid(),
            ],
        ];
    });
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);

    $first = freshPushAction()->handle($fix['charge']->id);

    expect($providerCalls)->toBe(1)
        ->and($transactionLevelAtProviderCall)->toBe($transactionLevelBeforePush)
        ->and($nestedResult)->toBeNull()
        ->and($first->installment_no)->toBe(1)
        ->and($fix['installments'][0]->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
        ->and($fix['installments'][1]->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and(DngPaymentRequest::query()->count())->toBe(1);
});

it('keeps an installment pending when provider success finalizes to target drift review', function () {
    $fix = createInstallmentPushFixture();
    $line = InvoiceLine::query()->where('charge_id', $fix['charge']->id)->sole();
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->once()->andReturn(['fake' => 'payload']);
    $mock->shouldReceive('insertNewRecord')->once()->andReturnUsing(function () use ($line): array {
        $line->update(['amount_snapshot' => '7400000.00']);

        return ['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []];
    });
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);

    $installment = freshPushAction()->handle($fix['charge']->id);
    $reservation = DngPaymentRequest::query()->findOrFail($installment->dng_payment_request_id);

    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($installment->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($installment->last_push_error)->toContain('requires Finance review')
        ->and($reservation->review_evidence['affected_installment_ids'])->toBe([$installment->id]);
});

it('lets staff reconcile a held reservation to pushed before awaiting payment', function () {
    $fix = createInstallmentPushFixture();
    $billingAccount = BillingAccount::query()->where('student_id', $fix['charge']->student_id)->sole();
    $installment = $fix['installments'][0];
    $reservation = DngPaymentRequest::query()->create([
        'student_id' => $fix['charge']->student_id,
        'billing_account_id' => $billingAccount->id,
        'campus_code' => 'TEST',
        'provider_rail' => 'dng',
        'student_code' => $fix['charge']->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Held installment',
        'semester_id' => $fix['charge']->semester_id,
        'due_date' => $installment->due_date,
        'item_id' => 'held-'.uniqid(),
        'active_slot_key' => 'held-slot-'.uniqid(),
        'amount' => $installment->amount,
        'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ]);
    $reservation->reservationTargets()->create([
        'invoice_line_id' => InvoiceLine::query()->where('charge_id', $fix['charge']->id)->sole()->id,
        'finance_charge_installment_id' => $installment->id,
        'captured_collectible' => $installment->amount,
        'target_identity' => 'installment:'.$installment->id,
    ]);
    $installment->update(['dng_payment_request_id' => $reservation->id]);

    $resolved = app(ResolveDngReservationOutcomeAction::class)->handle(
        $reservation,
        'pushed',
        ['provider_response' => ['Code' => 1, 'Message' => 'confirmed by staff']],
    );

    expect($resolved->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
        ->and($resolved->review_evidence['resolution'])->toBe('pushed');
});

it('returns a held installment to pending for terminal reconciliation outcomes', function (string $outcome, string $initialStatus, string $expectedStatus): void {
    $fix = createInstallmentPushFixture();
    $billingAccount = BillingAccount::query()->where('student_id', $fix['charge']->student_id)->sole();
    $installment = $fix['installments'][0];
    $reservation = DngPaymentRequest::query()->create([
        'student_id' => $fix['charge']->student_id,
        'billing_account_id' => $billingAccount->id,
        'campus_code' => 'TEST',
        'provider_rail' => 'dng',
        'student_code' => $fix['charge']->student->student_id,
        'fee_type' => 'HP',
        'description' => 'Held installment',
        'semester_id' => $fix['charge']->semester_id,
        'due_date' => $installment->due_date,
        'item_id' => 'held-'.uniqid(),
        'active_slot_key' => 'held-slot-'.uniqid(),
        'amount' => $installment->amount,
        'status' => $initialStatus,
    ]);
    $reservation->reservationTargets()->create([
        'invoice_line_id' => InvoiceLine::query()->where('charge_id', $fix['charge']->id)->sole()->id,
        'finance_charge_installment_id' => $installment->id,
        'captured_collectible' => $installment->amount,
        'target_identity' => 'installment:'.$installment->id,
    ]);
    $installment->update(['dng_payment_request_id' => $reservation->id]);

    $resolved = app(ResolveDngReservationOutcomeAction::class)->handle($reservation, $outcome, ['staff_note' => 'Provider outcome verified']);

    expect($resolved->status)->toBe($expectedStatus)
        ->and($resolved->active_slot_key)->toBeNull()
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($installment->fresh()->dng_payment_request_id)->toBe($reservation->id)
        ->and($resolved->review_evidence['resolution'])->toBe($outcome);
})->with([
    'unknown provider can restore pending' => ['restored_pending', DngPaymentRequest::STATUS_UNKNOWN_OUTCOME, DngPaymentRequest::STATUS_FAILED],
    'review can cancel the request' => ['cancelled', DngPaymentRequest::STATUS_NEEDS_REVIEW, DngPaymentRequest::STATUS_CANCELLED],
    'review can mark the request failed' => ['failed', DngPaymentRequest::STATUS_NEEDS_REVIEW, DngPaymentRequest::STATUS_FAILED],
]);

it('does not issue a second provider push after deterministic reconciliation confirms an ambiguous attempt', function (): void {
    $fix = createInstallmentPushFixture();
    mockDngClientPushFail('Connection timeout');

    try {
        freshPushAction()->handle($fix['charge']->id);
    } catch (Throwable) {
        // The reservation is intentionally retained for reconciliation.
    }

    $installment = $fix['installments'][0]->fresh();
    $reservation = DngPaymentRequest::query()->findOrFail($installment->dng_payment_request_id);
    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_UNKNOWN_OUTCOME);

    app(ResolveDngReservationOutcomeAction::class)->handle($reservation, 'pushed', [
        'provider_response' => ['ItemId' => $reservation->item_id, 'confirmed' => true],
    ]);
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldNotReceive('buildInsertNewRecordPayload');
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);

    expect(freshPushAction()->handle($fix['charge']->id))->toBeNull()
        ->and(DngPaymentRequest::query()->count())->toBe(1)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
});

// =========================================================================
// Case 9: settling DNG → installment paid + next push job dispatched
// =========================================================================
it('marks installment paid and dispatches next-push job when DNG settles', function () {
    Queue::fake();
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    // Push installment 1
    $installment1 = freshPushAction()->handle($fix['charge']->id);

    // Simulate DNG webhook settling the request
    $dng = DngPaymentRequest::find($installment1->dng_payment_request_id);
    $dng->update(['paid_at' => now()]);

    app(SettleInstallmentFromDngAction::class)->handle($dng);

    $installment1->refresh();
    expect($installment1->status)->toBe(FinanceChargeInstallment::STATUS_PAID);
    expect($installment1->paid_at)->not->toBeNull();

    // Next-push job should be queued.
    Queue::assertPushed(PushNextInstallmentJob::class, function ($job) use ($fix) {
        return $job->financeChargeId === $fix['charge']->id;
    });
});

// =========================================================================
// Case 10: job runs PushNextInstallmentAction → installment 2 awaiting
// =========================================================================
it('pushes installment 2 when installment 1 is already paid', function () {
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    // Mark installment 1 as already paid (precondition).
    $fix['installments'][0]->update([
        'status' => FinanceChargeInstallment::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $installment2 = freshPushAction()->handle($fix['charge']->id);

    expect($installment2)->not->toBeNull();
    expect($installment2->installment_no)->toBe(2);
    expect($installment2->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
});

// =========================================================================
// Case 11: DNG push fails → installment remains linked to the ambiguous reservation
// =========================================================================
it('records last_push_error and increments push_attempt_count when DNG push fails', function () {
    mockDngClientPushFail('Connection timeout');
    $fix = createInstallmentPushFixture();

    expect(fn () => freshPushAction()->handle($fix['charge']->id))
        ->toThrow(RuntimeException::class, 'Connection timeout');

    $installment = $fix['installments'][0]->fresh();
    expect($installment->status)->toBe(FinanceChargeInstallment::STATUS_PENDING);
    expect($installment->last_push_error)->toContain('Connection timeout');
    expect($installment->push_attempt_count)->toBe(1);
    expect($installment->dng_payment_request_id)->not->toBeNull();
    expect(DngPaymentRequest::query()->findOrFail($installment->dng_payment_request_id)->status)
        ->toBe(DngPaymentRequest::STATUS_UNKNOWN_OUTCOME);
});

// =========================================================================
// Case 11b: retry after fail succeeds → status awaiting, errors reset
// =========================================================================
it('holds an ambiguous provider outcome for reconciliation instead of retrying it', function () {
    $fix = createInstallmentPushFixture();

    // First attempt fails.
    mockDngClientPushFail('Transient 502');
    try {
        freshPushAction()->handle($fix['charge']->id);
    } catch (Throwable) {
        // expected
    }

    $i1 = $fix['installments'][0]->fresh();
    expect($i1->last_push_error)->toContain('Transient 502');
    expect($i1->push_attempt_count)->toBe(1);
    $reservation = DngPaymentRequest::query()->findOrFail($i1->dng_payment_request_id);
    expect((int) BillingAccount::query()->where('student_id', $fix['charge']->student_id)->sole()->settlement_version)
        ->toBe((int) $reservation->captured_settlement_version + 3);

    // A second push must not risk a duplicate provider debt.
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldNotReceive('buildInsertNewRecordPayload');
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);
    $retry = freshPushAction()->handle($fix['charge']->id);

    expect($retry)->not->toBeNull()
        ->and($retry->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($retry->last_push_error)->toContain('unknown_outcome')
        ->and($reservation->fresh()->review_evidence)->toMatchArray([
            'provider_response' => null,
            'provider_exception' => 'Transient 502',
            'deterministic_identity' => $reservation->item_id,
            'original_target_fingerprint' => $reservation->target_fingerprint,
        ])
        ->and(DngPaymentRequest::query()->count())->toBe(1);
});

// =========================================================================
// Case 12: no pending installment left → action returns null (settled)
// =========================================================================
it('returns null when there are no pending installments left', function () {
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldNotReceive('buildInsertNewRecordPayload');
    app()->instance(DngClient::class, $mock);
    app()->forgetInstance(DngPaymentService::class);
    $fix = createInstallmentPushFixture();

    // Mark both installments paid.
    foreach ($fix['installments'] as $i) {
        $i->update([
            'status' => FinanceChargeInstallment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    $billingAccount = BillingAccount::query()->where('student_id', $fix['charge']->student_id)->sole();
    $versionBefore = $billingAccount->settlement_version;

    $result = freshPushAction()->handle($fix['charge']->id);

    expect($result)->toBeNull()
        ->and($billingAccount->fresh()->settlement_version)->toBe($versionBefore)
        ->and(DngPaymentRequest::query()->count())->toBe(0);
});

it('does not provision a billing account when the charge has no installments', function () {
    $campus = Campus::factory()->withDngMapping('TEST')->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 15_000_000,
        'description' => 'No installment plan',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    BillingAccount::query()->where('student_id', $student->id)->delete();

    expect(freshPushAction()->handle($charge->id))->toBeNull()
        ->and(BillingAccount::query()->where('student_id', $student->id)->exists())->toBeFalse()
        ->and(DngPaymentRequest::query()->count())->toBe(0);
});

// =========================================================================
// Case 13: explicit later installment must not bypass sequence order
// =========================================================================
it('rejects an explicit later installment while an earlier one remains pending', function () {
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    expect(fn () => freshPushAction()->handle($fix['charge']->id, $fix['installments'][1]->id))
        ->toThrow(RuntimeException::class, 'not the next eligible installment');

    expect(DngPaymentRequest::query()->count())->toBe(0)
        ->and($fix['installments'][0]->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($fix['installments'][1]->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING);
});
