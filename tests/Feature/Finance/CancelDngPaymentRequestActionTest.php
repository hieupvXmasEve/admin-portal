<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngClient;
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
 * Create a CourseRetakeRegistration + FinanceCharge linked together.
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

it('cancels a pending request — voids the directly linked charge', function () {
    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($charge->fresh()->status)->toBe('void');
});

it('cancels a pending request — cancels the linked retake registration', function () {
    ['registration' => $reg, 'charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($reg->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('does not cancel a retake registration in terminal status when cancelling pending request', function () {
    ['registration' => $reg, 'charge' => $charge] = makeRetakeChargeLinked(
        $this->ctx,
        CourseRetakeRegistration::STATUS_ENROLLED // terminal — not cancellable
    );
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PENDING, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    // Registration stays enrolled; only the charge is voided
    expect($reg->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED);
    expect($charge->fresh()->status)->toBe('void');
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
});

it('cancels a pushed_to_dng request — voids directly linked charge', function () {
    mockDngClientSuccess();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($charge->fresh()->status)->toBe('void');
});

it('cancels a pushed_to_dng request — cancels the linked retake registration', function () {
    mockDngClientSuccess();

    ['registration' => $reg, 'charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($reg->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('cancels a pushed_to_dng request — voids all charges linked via chargeLinks pivot', function () {
    mockDngClientSuccess();

    // Two registrations / charges, linked via pivot (multi-retake batch)
    ['registration' => $regA, 'charge' => $chargeA] = makeRetakeChargeLinked($this->ctx);

    // Second registration for the same student in the same context
    $courseOfferingB = CourseOffering::factory()->create(['semester_id' => $this->ctx['semester']->id]);
    $academicRecordB = AcademicRecord::factory()->create([
        'student_id' => $this->ctx['student']->id,
        'campus_id' => $this->ctx['campus']->id,
        'unit_id' => $courseOfferingB->unit_id,
        'course_offering_id' => $courseOfferingB->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
    $regB = CourseRetakeRegistration::create([
        'student_id' => $this->ctx['student']->id,
        'unit_id' => $courseOfferingB->unit_id,
        'original_academic_record_id' => $academicRecordB->id,
        'course_offering_id' => $courseOfferingB->id,
        'semester_id' => $this->ctx['semester']->id,
        'campus_id' => $this->ctx['campus']->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 7000000,
        'approved_by_user_id' => $this->ctx['user']->id,
        'approved_at' => now(),
    ]);
    $chargeB = FinanceCharge::create([
        'student_id' => $this->ctx['student']->id,
        'semester_id' => $this->ctx['semester']->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 7000000,
        'description' => 'Retake B',
        'effective_at' => now()->addSecond(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class,
        'source_id' => $regB->id,
    ]);
    $regB->update(['finance_charge_id' => $chargeB->id]);

    // Aggregate DNG request (no direct finance_charge_id — uses pivot instead)
    $request = DngPaymentRequest::create([
        'student_id' => $this->ctx['student']->id,
        'campus_code' => 'HCM',
        'student_code' => 'STU001',
        'fee_type' => 'HL',
        'item_id' => 'ITEM-BATCH-001',
        'amount' => 12000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => null,
        'push_payload' => [
            'StudentName' => 'Test Student',
            'Email' => 'test@example.com',
            'EstimateTime' => '2026-05-01T00:00:00',
            'StudentAddress' => '123 Main St',
        ],
    ]);

    // Create pivot links
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $chargeA->id,
        'amount' => 5000000,
    ]);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $chargeB->id,
        'amount' => 7000000,
    ]);

    app(CancelDngPaymentRequestAction::class)->run($request);

    expect($chargeA->fresh()->status)->toBe('void');
    expect($chargeB->fresh()->status)->toBe('void');
    expect($regA->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
    expect($regB->fresh()->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('DNG API failure — does not change status', function () {
    mockDngClientFailure();

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

it('DNG API failure — does not void linked charge', function () {
    mockDngClientFailure();

    ['charge' => $charge] = makeRetakeChargeLinked($this->ctx);
    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG, $charge);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    expect($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('DNG API failure — stores attempted cancel payload for audit', function () {
    mockDngClientFailure();

    $request = makeDngRequest($this->ctx, DngPaymentRequest::STATUS_PUSHED_TO_DNG);

    expect(fn () => app(CancelDngPaymentRequestAction::class)->run($request))
        ->toThrow(RuntimeException::class);

    // cancel_push_payload should be persisted even on failure for audit trail
    expect($request->fresh()->cancel_push_payload)->not->toBeNull();
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
