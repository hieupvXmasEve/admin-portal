<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\PushNextInstallmentAction;
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
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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
    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
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

    $i1 = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 7_500_000,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);
    $i2 = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 2,
        'amount' => 7_500_000,
        'due_date' => now()->addDays(60)->toDateString(),
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
it('pushes the next pending installment to DNG and links the record', function () {
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    $installment = freshPushAction()->handle($fix['charge']->id);

    expect($installment)->not->toBeNull();
    expect($installment->installment_no)->toBe(1);
    expect($installment->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
    expect($installment->dng_payment_request_id)->not->toBeNull();
    expect($installment->last_push_error)->toBeNull();

    $dng = DngPaymentRequest::query()->find($installment->dng_payment_request_id);
    expect($dng)->not->toBeNull();
    expect((float) $dng->amount)->toBe(7_500_000.0);
    expect($dng->finance_charge_id)->toBeNull()
        ->and($dng->chargeLinks->sole()->finance_charge_id)->toBe($fix['charge']->id)
        ->and($dng->chargeLinks->sole()->finance_charge_installment_id)->toBe($installment->id);
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
// Case 11: DNG push fails → installment stays pending with last_push_error
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
    expect($installment->dng_payment_request_id)->toBeNull();
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

    // A second push must not risk a duplicate provider debt.
    mockDngClientPushSuccess();
    expect(fn () => freshPushAction()->handle($fix['charge']->id))
        ->toThrow(RuntimeException::class, 'unknown provider outcome');
    expect($fix['installments'][0]->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING);
});

// =========================================================================
// Case 12: no pending installment left → action returns null (settled)
// =========================================================================
it('returns null when there are no pending installments left', function () {
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    // Mark both installments paid.
    foreach ($fix['installments'] as $i) {
        $i->update([
            'status' => FinanceChargeInstallment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    $result = freshPushAction()->handle($fix['charge']->id);
    expect($result)->toBeNull();
});

// =========================================================================
// Case 13: manual retry with explicit installment_id → action targets it
// =========================================================================
it('honors explicit installment_id (manual retry button)', function () {
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    // Bypass installment 1, retry installment 2 directly.
    // (Real flow: i1 awaiting, i2 pending. Admin can't manual-retry i2 while
    // i1 is awaiting because DNG invariant blocks. But action itself is
    // explicit — test verifies the resolveTarget path.)
    $result = freshPushAction()->handle($fix['charge']->id, $fix['installments'][1]->id);

    expect($result->installment_no)->toBe(2);
});
