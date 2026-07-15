<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Actions\Major\SubmitTuitionTermDebitAction;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Jobs\PushNextInstallmentJob;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function batch_mockCampusCodeResolver(string $code = 'TEST'): void
{
    $mock = Mockery::mock(DngCampusCodeResolver::class);
    $mock->shouldReceive('requireForStudent')->andReturn($code);
    app()->instance(DngCampusCodeResolver::class, $mock);
}

function batch_mockDngClientPushSuccess(): void
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

beforeEach(function () {
    batch_mockCampusCodeResolver();
});

/**
 * Build a tuition_term charge with N installments for a student in a specific semester.
 * Wave-3: HP DNG requires an accepted FinanceObligation on tuition_term payables.
 */
function makeBatchChargeWithInstallments(int $studentId, int $semesterId, float $gross, int $installmentCount): FinanceCharge
{
    $billingAccount = app(BillingAccountProvisioner::class)->forStudent($studentId);

    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_LEGACY_TUITION,
        'source_ref' => FinanceOwnedObligationSource::legacyTuitionTermChargeRef(
            // provisional; updated after charge insert for stable uniqueness
            random_int(1_000_000, 9_999_999)
        ),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $gross,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition_term:test',
        'pricing_snapshot' => ['provenance' => 'test_fixture'],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $studentId,
        'semester_id' => $semesterId,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $gross,
        'description' => 'HP test',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'finance_obligation_id' => $obligation->id,
    ]);

    $obligation->update([
        'source_ref' => FinanceOwnedObligationSource::legacyTuitionTermChargeRef((int) $charge->id),
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-BATCH-'.uniqid(),
        'student_id' => $studentId,
        'semester_id' => $semesterId,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $gross,
        'description_snapshot' => 'HP batch test',
        'status' => 'active',
    ]);

    $per = $gross / $installmentCount;
    for ($i = 1; $i <= $installmentCount; $i++) {
        FinanceChargeInstallment::factory()->create([
            'finance_charge_id' => $charge->id,
            'installment_no' => $i,
            'amount' => $per,
            'due_date' => now()->addDays(30 * $i)->toDateString(),
        ]);
    }

    return $charge->fresh(['installments']);
}

// =========================================================================
// Worklist push respects installment plan — single charge, 2 installments
// =========================================================================
it('worklist push uses next-pending installment amount (not full charge balance)', function () {
    batch_mockDngClientPushSuccess();

    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    // Charge 20M split into 2x10M.
    $charge = makeBatchChargeWithInstallments($student->id, $semester->id, 20_000_000, 2);

    $batch = app()->forgetInstance(CreateBatchDngFromChargesAction::class)
        ? null
        : null;
    app()->forgetInstance(CreateBatchDngFromChargesAction::class);
    $batch = app(CreateBatchDngFromChargesAction::class);

    $result = $batch->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'HP semester',
        'estimate_time' => now()->addDays(30)->toDateString(),
    ]);

    expect($result['created'])->toBe(1);
    expect($result['failed'])->toBe(0);

    // Pushed DNG amount = 10M (installment 1), NOT 20M.
    $dng = DngPaymentRequest::query()->where('student_id', $student->id)->latest('id')->first();
    expect((float) $dng->amount)->toBe(10_000_000.0);

    // Installment 1 linked + awaiting; installment 2 still pending.
    $charge->load('installments');
    $i1 = $charge->installments->where('installment_no', 1)->first();
    $i2 = $charge->installments->where('installment_no', 2)->first();
    expect($i1->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
    expect($i1->dng_payment_request_id)->toBe($dng->id);
    expect($i2->status)->toBe(FinanceChargeInstallment::STATUS_PENDING);
});

// =========================================================================
// Worklist bundle of 2 charges with installments → 1 DNG with summed
// next-pending amounts, both installments linked
// =========================================================================
it('worklist bundles next-pending across multiple charges of same student', function () {
    batch_mockDngClientPushSuccess();

    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    // Charge A: 20M split 2x10M. Charge B: 6M un-split (1 installment of 6M).
    $chargeA = makeBatchChargeWithInstallments($student->id, $semester->id, 20_000_000, 2);
    $chargeB = makeBatchChargeWithInstallments($student->id, $semester->id, 6_000_000, 1);

    app()->forgetInstance(CreateBatchDngFromChargesAction::class);
    $batch = app(CreateBatchDngFromChargesAction::class);

    $result = $batch->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'HP semester',
        'estimate_time' => now()->addDays(30)->toDateString(),
    ]);

    expect($result['created'])->toBe(1);

    $dng = DngPaymentRequest::query()->where('student_id', $student->id)->latest('id')->first();
    // Sum of A.installment_1 (10M) + B.installment_1 (6M) = 16M.
    expect((float) $dng->amount)->toBe(16_000_000.0);

    // Both installments linked + awaiting.
    $a1 = $chargeA->installments->where('installment_no', 1)->first()->fresh();
    $b1 = $chargeB->installments->where('installment_no', 1)->first()->fresh();
    expect($a1->dng_payment_request_id)->toBe($dng->id);
    expect($b1->dng_payment_request_id)->toBe($dng->id);
    expect($a1->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
    expect($b1->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);

    // FIN-10b: pivot amounts must mirror the INSTALLMENTS being collected
    // (A=10M, B=6M), NOT a balance-proportional split (which would be ~12.3M/3.7M
    // since A balance 20M, B balance 6M). Each pivot also links its installment.
    $pivotA = DngPaymentRequestCharge::where('dng_payment_request_id', $dng->id)
        ->where('finance_charge_id', $chargeA->id)->first();
    $pivotB = DngPaymentRequestCharge::where('dng_payment_request_id', $dng->id)
        ->where('finance_charge_id', $chargeB->id)->first();

    expect((float) $pivotA->amount)->toBe(10_000_000.0)
        ->and((float) $pivotB->amount)->toBe(6_000_000.0)
        ->and($pivotA->finance_charge_installment_id)->toBe($a1->id)
        ->and($pivotB->finance_charge_installment_id)->toBe($b1->id);

    // Invariant: request amount == Σ pivot == Σ linked installment.
    $pivotSum = (float) DngPaymentRequestCharge::where('dng_payment_request_id', $dng->id)->sum('amount');
    expect($pivotSum)->toBe(16_000_000.0)
        ->and($pivotSum)->toBe((float) $dng->amount);
});

// =========================================================================
// Settle multi-installment DNG → all installments paid + per-charge push jobs
// =========================================================================
it('settling a bundled DNG settles ALL linked installments and dispatches per-charge push jobs', function () {
    Queue::fake();
    batch_mockDngClientPushSuccess();

    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $chargeA = makeBatchChargeWithInstallments($student->id, $semester->id, 20_000_000, 2);
    $chargeB = makeBatchChargeWithInstallments($student->id, $semester->id, 6_000_000, 1);

    app()->forgetInstance(CreateBatchDngFromChargesAction::class);
    app(CreateBatchDngFromChargesAction::class)->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'HP semester',
        'estimate_time' => now()->addDays(30)->toDateString(),
    ]);

    $dng = DngPaymentRequest::query()->where('student_id', $student->id)->latest('id')->first();
    $dng->update(['paid_at' => now()]);

    $settled = app(SettleInstallmentFromDngAction::class)->handle($dng);

    // Both installments settled in this call.
    expect($settled)->toHaveCount(2);
    expect($settled->pluck('status')->unique()->all())->toBe(['paid']);

    // ChargeA has installment 2 pending → push job dispatched.
    // ChargeB has no more pending → no job for B.
    Queue::assertPushed(PushNextInstallmentJob::class, 1);
    Queue::assertPushed(PushNextInstallmentJob::class, fn ($job) => $job->financeChargeId === $chargeA->id);
    Queue::assertNotPushed(PushNextInstallmentJob::class, fn ($job) => $job->financeChargeId === $chargeB->id);
});

// =========================================================================
// Amount override that EQUALS installment-aware sum → still links installments
// =========================================================================
it('still links installments when amount_override equals the auto-computed sum', function () {
    batch_mockDngClientPushSuccess();

    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $charge = makeBatchChargeWithInstallments($student->id, $semester->id, 20_000_000, 2);

    app()->forgetInstance(CreateBatchDngFromChargesAction::class);
    app(CreateBatchDngFromChargesAction::class)->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'HP semester',
        'estimate_time' => now()->addDays(30)->toDateString(),
        'amount_overrides' => [$student->id => 10_000_000],
    ]);

    $dng = DngPaymentRequest::query()->where('student_id', $student->id)->latest('id')->first();
    expect((float) $dng->amount)->toBe(10_000_000.0);

    $i1 = $charge->installments->where('installment_no', 1)->first()->fresh();
    expect($i1->dng_payment_request_id)->toBe($dng->id);
    expect($i1->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
});

// =========================================================================
// Amount override that DIFFERS from installment-aware sum → skip linkage
// =========================================================================
it('ignores a legacy amount_override and keeps the canonical installment target', function () {
    batch_mockDngClientPushSuccess();

    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $charge = makeBatchChargeWithInstallments($student->id, $semester->id, 20_000_000, 2);

    app()->forgetInstance(CreateBatchDngFromChargesAction::class);
    app(CreateBatchDngFromChargesAction::class)->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'HP partial',
        'estimate_time' => now()->addDays(30)->toDateString(),
        'amount_overrides' => [$student->id => 5_000_000],
    ]);

    $dng = DngPaymentRequest::query()->where('student_id', $student->id)->latest('id')->first();
    expect((float) $dng->amount)->toBe(10_000_000.0);

    $i1 = $charge->installments->where('installment_no', 1)->first()->fresh();
    expect($i1->dng_payment_request_id)->toBe($dng->id);
    expect($i1->status)->toBe(FinanceChargeInstallment::STATUS_AWAITING_PAYMENT);
});

// =========================================================================
// Backward-compat: legacy charge without installment row → falls back to balance
// =========================================================================
it('worklist falls back to charge.balance when a charge has no installment row (legacy)', function () {
    batch_mockDngClientPushSuccess();

    $campus = Campus::factory()->create(['dng_code' => 'TEST']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    // Charge with NO installments (legacy / never backfilled installments),
    // but still obligation-linked (wave-3 DNG guard).
    $billingAccount = app(BillingAccountProvisioner::class)->forStudent($student->id);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_LEGACY_TUITION,
        'source_ref' => 'legacy:tuition_term:test:no-installment',
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 5_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition_term:test',
        'pricing_snapshot' => ['provenance' => 'test_fixture'],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5_000_000,
        'description' => 'Legacy HP',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'finance_obligation_id' => $obligation->id,
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-BATCH-LEGACY-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 5_000_000,
        'description_snapshot' => 'Legacy HP batch test',
        'status' => 'active',
    ]);

    app()->forgetInstance(CreateBatchDngFromChargesAction::class);
    $result = app(CreateBatchDngFromChargesAction::class)->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(30)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'HP semester',
        'estimate_time' => now()->addDays(30)->toDateString(),
    ]);

    expect($result['created'])->toBe(1);
    $dng = DngPaymentRequest::query()->where('student_id', $student->id)->latest('id')->first();
    // Falls back to charge balance = 5M.
    expect((float) $dng->amount)->toBe(5_000_000.0);
});
