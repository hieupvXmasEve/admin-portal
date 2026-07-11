<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\FinanceCharge;
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
 * Create a FinanceCharge linked to a source record.
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

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class,
        'source_id' => $registration->id,
    ]);

    $registration->update(['finance_charge_id' => $charge->id]);

    return compact('registration', 'charge');
}

/**
 * Create a DNG payment request with given status, optionally linked to a charge.
 */
function makeDngRequest(array $context, string $status, ?FinanceCharge $charge = null): DngPaymentRequest
{
    ['student' => $student] = $context;

    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'HCM',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-TEST-001',
        'amount' => 5000000,
        'status' => $status,
        'finance_charge_id' => $charge?->id,
        'push_payload' => [
            'StudentName' => 'Test Student',
            'Email' => 'test@example.com',
            'EstimateTime' => '2026-05-01T00:00:00',
            'StudentAddress' => '123 Main St',
            'CCCD' => '000000000001',
        ],
    ]);
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

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
});

it('cancels a pending request without voiding the directly linked charge or source workflow', function () {
    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($charge->fresh()->source_id)->not->toBeNull();
});

it('cancels a pushed_to_dng request — calls DNG API and transitions to cancel_pushed_to_dng', function () {
    mockDngClientSuccess();

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG);
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
