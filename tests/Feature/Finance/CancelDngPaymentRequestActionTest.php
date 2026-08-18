<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create a minimal student + dependencies for a retake charge test.
 */
function makeCancelTestStudent(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    return compact('campus', 'semester', 'user', 'student');
}

/**
 * Create a FinanceCharge through the canonical source obligation.
 */
function makeRetakeChargeLinked(array $context, string $regStatus = CourseRetakeRegistration::STATUS_PAYMENT_PENDING): array
{
    ['campus' => $campus, 'semester' => $semester, 'user' => $user, 'student' => $student] = $context;

    $courseOffering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    $registration = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => $regStatus,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    $obligation = FinanceObligation::create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 5_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['test' => true],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return compact('registration', 'charge');
}

/**
 * Legacy wire-shaped push payload (requests pushed before guarded reservations).
 *
 * @return array<string, string>
 */
function legacyPascalCasePushPayload(): array
{
    return [
        'StudentName' => 'Test Student',
        'Email' => 'test@example.com',
        'EstimateTime' => '2026-05-01T00:00:00',
        'StudentAddress' => '123 Main St',
        'CCCD' => '000000000001',
    ];
}

/**
 * Payload shape DngReservationLifecycle::providerPayload() actually stores.
 *
 * @return array<string, string>
 */
function guardedReservationPushPayload(): array
{
    return [
        'campus_code' => 'HCM',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'type' => 'HL',
        'description' => 'Học lại',
        'semester_id' => '1',
        'due_date' => '2026-05-01',
        'item_id' => 'ITEM-TEST-001',
        'amount' => '5000000',
        'student_name' => 'Test Student',
        'email' => 'test@example.com',
        'estimate_time' => '05/26',
        'student_address' => '123 Main St',
        'cccd' => '000000000001',
    ];
}

/**
 * Create a DNG payment request with given status, optionally linked to a charge.
 *
 * @param  array<string, string>|null  $pushPayload  defaults to the legacy wire shape
 */
function makeDngRequest(array $context, string $status, ?FinanceCharge $charge = null, ?array $pushPayload = null): DngPaymentRequest
{
    ['student' => $student] = $context;
    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);

    $request = DngPaymentRequest::create([
        'student_id' => $student->id,
        'billing_account_id' => $billingAccount->id,
        'campus_code' => 'HCM',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-TEST-001',
        'amount' => 5000000,
        'status' => $status,
        'push_payload' => $pushPayload ?? legacyPascalCasePushPayload(),
    ]);

    if ($charge !== null) {
        DngPaymentRequestCharge::create([
            'dng_payment_request_id' => $request->id,
            'finance_charge_id' => $charge->id,
            'amount' => $charge->amount,
        ]);
    }

    return $request;
}

/**
 * Bind a mock DngClient that returns a success response on cancelRecord.
 */
function mockDngClientSuccess(): void
{
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['mock' => 'payload']);
    $mock->shouldReceive('cancelRecord')->andReturn(['ResponseCode' => '00', 'Message' => 'OK']);
    app()->instance(DngClient::class, $mock);
}

/**
 * Bind a mock DngClient that throws on cancelRecord.
 */
function mockDngClientFailure(): void
{
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['mock' => 'payload']);
    $mock->shouldReceive('cancelRecord')->andThrow(new RuntimeException('DNG API error'));
    app()->instance(DngClient::class, $mock);
}

// ─── beforeEach ───────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->ctx = makeCancelTestStudent();
    $this->actingAs($this->ctx['user']);
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('cancels a pending request — transitions to cancelled', function () {
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING);
    $billingAccount = BillingAccount::query()->findOrFail($request->billing_account_id);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and((int) $billingAccount->fresh()->settlement_version)->toBe(1);
});

it('does not advance settlement version for an already cancelled local lifecycle request', function (): void {
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_CANCELLED);
    $billingAccount = BillingAccount::query()->findOrFail($request->billing_account_id);

    app(CancelDngPaymentRequestAction::class)->runLocallyForLifecycle($request);

    expect((int) $billingAccount->fresh()->settlement_version)->toBe(0);
});

it('cancels a pending request without voiding the directly linked charge or source workflow', function () {
    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($request->fresh()->chargeLinks->sole()->finance_charge_id)->toBe($charge->id);
});

it('cancels a pushed_to_dng request — calls DNG API and transitions to cancel_pushed_to_dng', function () {
    $transactionLevelBefore = DB::transactionLevel();
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['mock' => 'payload']);
    $mock->shouldReceive('cancelRecord')->once()->andReturnUsing(function () use ($transactionLevelBefore): array {
        expect(DB::transactionLevel())->toBe($transactionLevelBefore);

        return ['ResponseCode' => '00', 'Message' => 'OK'];
    });
    app()->instance(DngClient::class, $mock);

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);
    $billingAccount = BillingAccount::query()->findOrFail($request->billing_account_id);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG)
        ->and((int) $billingAccount->fresh()->settlement_version)->toBe(1);
});

it('cancels a pushed request locally for a lifecycle closure without calling DNG', function () {
    $client = Mockery::mock(DngClient::class);
    $client->shouldNotReceive('buildInsertNewRecordPayload');
    $client->shouldNotReceive('cancelRecord');
    app()->instance(DngClient::class, $client);

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    app(CancelDngPaymentRequestAction::class)->runLocallyForLifecycle($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED)
        ->and($request->fresh()->cancel_push_payload)->toBeNull()
        ->and($request->fresh()->cancel_push_response)->toBeNull();
});

it('locally closes every unpaid review state for a terminal lifecycle', function (string $status) {
    $request = makeDngRequest($this->ctx, $status);

    app(CancelDngPaymentRequestAction::class)->runLocallyForLifecycle($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
})->with([
    DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
    DngPaymentRequest::STATUS_NEEDS_REVIEW,
]);

it('refuses local lifecycle closure when canonical payment is already linked', function () {
    $payment = Payment::query()->create([
        'student_id' => $this->ctx['student']->id,
        'amount' => 1_000_000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);
    $request->update(['payment_id' => $payment->id]);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->runLocallyForLifecycle($request))
        ->toThrow(RuntimeException::class, 'canonical Payment');

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('cancels a pushed_to_dng request — stores cancel payload and response for audit', function () {
    mockDngClientSuccess();

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    app(CancelDngPaymentRequestAction::class)->run($request);

    $fresh = $request->fresh();
    expect($fresh->cancel_push_payload)->not->toBeNull();
    expect($fresh->cancel_push_response)->not->toBeNull();
    expect(DngReceiptException::query()->where('exception_type', 'cancel_confirmed')->count())->toBe(1);
});

it('cancels a pushed_to_dng request without voiding directly linked charge', function () {
    mockDngClientSuccess();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('DNG API failure becomes an unknown outcome and keeps collection blocked', function () {
    mockDngClientFailure();

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_UNKNOWN_OUTCOME)
        ->and($request->fresh()->cancel_push_payload)->not->toBeNull();
});

it('DNG API failure does not void linked charge', function () {
    mockDngClientFailure();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('DNG API failure records one idempotent exception for reconciliation', function () {
    mockDngClientFailure();

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    expect($request->fresh()->cancel_push_payload)->not->toBeNull()
        ->and(DngReceiptException::query()->where('exception_type', 'unknown_collection_outcome')->count())->toBe(1);
});

it('does not classify an explicit provider rejection as an unknown outcome', function () {
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['mock' => 'payload']);
    $mock->shouldReceive('cancelRecord')->andThrow(new RuntimeException('DNG API error [422]: cancellation denied'));
    app()->instance(DngClient::class, $mock);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and(DngReceiptException::query()->where('exception_type', 'cancel_rejected')->count())->toBe(1);
});

it('throws when attempting to cancel a non-cancellable status', function () {
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PAID_UNINVOICED);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);
});

it('cancels a pending request when the linked charge is already voided', function () {
    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $charge->update([
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => now(),
        'void_reason' => 'Voided before DNG cancel',
    ]);

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
});

it('cancels a pushed_to_dng request when the linked charge is already voided', function () {
    mockDngClientSuccess();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $charge->update([
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => now(),
        'void_reason' => 'Voided before DNG cancel',
    ]);

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG);
});

// ─── Payer echo-back + installment release ────────────────────────────────────

it('echoes payer fields to DNG from a guarded-reservation push payload', function (array $pushPayload) {
    $captured = null;
    $mock = Mockery::mock(DngClient::class);
    $mock->shouldReceive('buildInsertNewRecordPayload')->andReturn(['mock' => 'payload']);
    $mock->shouldReceive('cancelRecord')->once()->andReturnUsing(function (array $originalData) use (&$captured): array {
        $captured = $originalData;

        return ['ResponseCode' => '00', 'Message' => 'OK'];
    });
    app()->instance(DngClient::class, $mock);

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, null, $pushPayload);

    app(CancelDngPaymentRequestAction::class)->run($request);

    // Empty payer fields make DNG reject the cancellation with a 403
    // "Email không để trống", stranding the request in needs_review.
    expect($captured['email'])->toBe('test@example.com')
        ->and($captured['student_name'])->toBe('Test Student')
        ->and($captured['estimate_time'])->not->toBe('')
        ->and($captured['student_address'])->toBe('123 Main St')
        ->and($captured['cccd'])->toBe('000000000001');
})->with([
    'guarded reservation (snake_case)' => [guardedReservationPushPayload()],
    'legacy wire payload (PascalCase)' => [legacyPascalCasePushPayload()],
]);

it('returns an awaiting installment to pending when its collection is cancelled', function (string $mode) {
    mockDngClientSuccess();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $status = $mode === 'lifecycle'
        ? DngPaymentRequest::STATUS_PUSHED_TO_DNG
        : DngPaymentRequest::STATUS_PENDING;
    $request = makeDngRequest($this->ctx, $status, $charge);

    $installment = FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => $charge->amount,
        'due_date' => now()->addMonth()->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $request->id,
    ]);

    $mode === 'lifecycle'
        ? app(CancelDngPaymentRequestAction::class)->runLocallyForLifecycle($request)
        : app(CancelDngPaymentRequestAction::class)->run($request);

    // PushNextInstallmentAction only accepts a pending next installment; an
    // installment left awaiting_payment behind a dead request cannot be re-pushed
    // and fails silently (returns null, no error).
    expect($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($installment->fresh()->last_push_error)->toContain('cancelled');
})->with(['pending', 'lifecycle']);

it('releases the awaiting installment after a provider-confirmed cancellation', function () {
    mockDngClientSuccess();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    $installment = FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => $charge->amount,
        'due_date' => now()->addMonth()->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $request->id,
    ]);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING);
});

it('leaves a paid installment settled when its collection is cancelled', function () {
    mockDngClientSuccess();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    $installment = FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => $charge->amount,
        'due_date' => now()->addMonth()->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_PAID,
        'dng_payment_request_id' => $request->id,
    ]);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PAID);
});
