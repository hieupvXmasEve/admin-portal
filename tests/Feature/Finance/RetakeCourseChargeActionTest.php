<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createChargeTestRegistration(array $overrides = []): CourseRetakeRegistration
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
    $user = User::factory()->create();

    return CourseRetakeRegistration::create(array_merge([
        'student_id' => $student->id,
        'unit_id' => $academicRecord->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_APPROVED,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Mock DNG services. createAndPush must return a persisted DngPaymentRequest so
    // the action can insert dng_payment_request_charges FK rows against a real DB id.
    $mockDngService = Mockery::mock(DngPaymentService::class);
    $mockDngService
        ->shouldReceive('createAndPush')
        ->andReturnUsing(function ($student, array $payload) {
            return DngPaymentRequest::create([
                'student_id' => $student->id,
                'campus_code' => $payload['campus_code'] ?? 'HCM',
                'student_code' => $payload['student_code'] ?? $student->student_id,
                'fee_type' => $payload['fee_type'] ?? 'HL',
                'item_id' => $payload['item_id'] ?? 'ITEM-MOCK',
                'amount' => $payload['amount'] ?? 0,
                'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                'finance_charge_id' => null,
            ]);
        });
    app()->instance(DngPaymentService::class, $mockDngService);

    $mockResolver = Mockery::mock(DngCampusCodeResolver::class);
    $mockResolver->shouldReceive('requireForStudent')->andReturn('HCM');
    app()->instance(DngCampusCodeResolver::class, $mockResolver);
});

it('creates finance charge for approved registration', function () {
    $reg = createChargeTestRegistration();

    $action = app(CreateRetakeCourseChargeAction::class);
    $result = $action->handle([
        'registration_id' => $reg->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($result->finance_charge_id)->not->toBeNull();
    expect($result->charge_created_by_user_id)->toBe($this->user->id);
    expect($result->charge_created_at)->not->toBeNull();

    // Verify FinanceCharge was created
    $charge = FinanceCharge::find($result->finance_charge_id);
    expect($charge)->not->toBeNull();
    expect($charge->charge_type)->toBe(FinanceCharge::TYPE_RETAKE_FEE);
    expect((float) $charge->amount)->toBe(5000000.00);
    expect($charge->source_type)->toBe(CourseRetakeRegistration::class);
    expect($charge->source_id)->toBe($reg->id);
    expect($charge->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('reuses an existing active source charge for an approved registration', function () {
    $reg = createChargeTestRegistration();
    $existingCharge = FinanceCharge::create([
        'student_id' => $reg->student_id,
        'semester_id' => $reg->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Pre-existing HQ retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class,
        'source_id' => $reg->id,
    ]);

    $action = app(CreateRetakeCourseChargeAction::class);
    $result = $action->handle([
        'registration_id' => $reg->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($result->finance_charge_id)->toBe($existingCharge->id);
    expect(FinanceCharge::query()
        ->where('source_type', CourseRetakeRegistration::class)
        ->where('source_id', $reg->id)
        ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->count())->toBe(1);
});

it('uses custom amount when provided', function () {
    $reg = createChargeTestRegistration();

    $action = app(CreateRetakeCourseChargeAction::class);
    $result = $action->handle([
        'registration_id' => $reg->id,
        'amount' => 3000000,
    ]);

    $charge = FinanceCharge::find($result->finance_charge_id);
    expect((float) $charge->amount)->toBe(3000000.00);
});

it('sets payment_deadline when provided', function () {
    $reg = createChargeTestRegistration();
    $deadline = now()->addDays(30)->format('Y-m-d');

    $action = app(CreateRetakeCourseChargeAction::class);
    $result = $action->handle([
        'registration_id' => $reg->id,
        'payment_deadline' => $deadline,
    ]);

    expect($result->payment_deadline->format('Y-m-d'))->toBe($deadline);
});

it('rejects DNG creation for terminal/paid registration', function () {
    // paid, enrolled, cancelled are not allowed — only approved and payment_pending are.
    $reg = createChargeTestRegistration(['status' => CourseRetakeRegistration::STATUS_PAID]);

    $action = app(CreateRetakeCourseChargeAction::class);
    $action->handle([
        'registration_id' => $reg->id,
    ]);
})->throws(ValidationException::class);

it('creates aggregate DNG request with pivot rows when student has multiple payment_pending registrations', function () {
    // Arrange: two payment_pending registrations (both already have a FinanceCharge)
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $offeringA = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $offeringB = CourseOffering::factory()->create(['semester_id' => $semester->id]);

    $academicRecordA = AcademicRecord::factory()->create([
        'student_id' => $student->id, 'campus_id' => $campus->id,
        'unit_id' => $offeringA->unit_id, 'course_offering_id' => $offeringA->id,
        'completion_status' => 'failed', 'is_passed' => false,
    ]);
    $academicRecordB = AcademicRecord::factory()->create([
        'student_id' => $student->id, 'campus_id' => $campus->id,
        'unit_id' => $offeringB->unit_id, 'course_offering_id' => $offeringB->id,
        'completion_status' => 'failed', 'is_passed' => false,
    ]);

    // Registration A — will be the one passed to handle()
    $regA = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $offeringA->unit_id,
        'original_academic_record_id' => $academicRecordA->id,
        'course_offering_id' => $offeringA->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $chargeA = FinanceCharge::create([
        'student_id' => $student->id, 'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000, 'description' => 'Retake A',
        'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class, 'source_id' => $regA->id,
    ]);
    $regA->update(['finance_charge_id' => $chargeA->id]);

    // Registration B — sibling registration, already payment_pending with a charge
    $regB = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $offeringB->unit_id,
        'original_academic_record_id' => $academicRecordB->id,
        'course_offering_id' => $offeringB->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 7000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $chargeB = FinanceCharge::create([
        'student_id' => $student->id, 'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 7000000, 'description' => 'Retake B',
        'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class, 'source_id' => $regB->id,
    ]);
    $regB->update(['finance_charge_id' => $chargeB->id]);

    // Pre-create the DNG request row so FK constraints pass in the test DB
    $fakeDngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'HCM',
        'student_code' => $student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'ITEM-AGG-TEST',
        'amount' => 12000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => null,
    ]);

    $mockDngService = Mockery::mock(DngPaymentService::class);
    $mockDngService
        ->shouldReceive('createAndPush')
        ->once()
        ->withArgs(function ($studentArg, array $payload) use ($student) {
            // Assert total amount is aggregated (5M + 7M)
            expect($studentArg->id)->toBe($student->id);
            expect($payload['amount'])->toBe(12000000.0);
            expect($payload['finance_charge_id'])->toBeNull();

            return true;
        })
        ->andReturn($fakeDngRequest);
    app()->instance(DngPaymentService::class, $mockDngService);

    $mockResolver = Mockery::mock(DngCampusCodeResolver::class);
    $mockResolver->shouldReceive('requireForStudent')->andReturn('HCM');
    app()->instance(DngCampusCodeResolver::class, $mockResolver);

    // Act: trigger from registration A
    $action = app(CreateRetakeCourseChargeAction::class);
    $action->handle(['registration_id' => $regA->id]);

    // Assert: pivot table has 2 rows (one per charge)
    $this->assertDatabaseHas('dng_payment_request_charges', [
        'dng_payment_request_id' => $fakeDngRequest->id,
        'finance_charge_id' => $chargeA->id,
        'amount' => 5000000,
    ]);
    $this->assertDatabaseHas('dng_payment_request_charges', [
        'dng_payment_request_id' => $fakeDngRequest->id,
        'finance_charge_id' => $chargeB->id,
        'amount' => 7000000,
    ]);
});

it('handles payment_pending registration triggered again (re-create DNG with all current pending charges)', function () {
    // If "Tạo DNG" is called for a registration already in payment_pending,
    // it should still collect all pending charges and build one aggregate DNG.
    $reg = createChargeTestRegistration(['status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING]);
    $charge = FinanceCharge::create([
        'student_id' => $reg->student_id, 'semester_id' => $reg->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000, 'description' => 'Retake',
        'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class, 'source_id' => $reg->id,
    ]);
    $reg->update(['finance_charge_id' => $charge->id]);

    // Pre-create a DNG request in DB so FK constraints pass
    $fakeDngRequest2 = DngPaymentRequest::create([
        'student_id' => $reg->student_id,
        'campus_code' => 'HCM',
        'student_code' => $reg->student->student_id,
        'fee_type' => 'HL',
        'item_id' => 'ITEM-SINGLE-TEST',
        'amount' => 5000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => null,
    ]);

    $mockDngService = Mockery::mock(DngPaymentService::class);
    $mockDngService
        ->shouldReceive('createAndPush')
        ->once()
        ->withArgs(function ($studentArg, array $payload) {
            expect($payload['amount'])->toBe(5000000.0);

            return true;
        })
        ->andReturn($fakeDngRequest2);
    app()->instance(DngPaymentService::class, $mockDngService);

    $mockResolver = Mockery::mock(DngCampusCodeResolver::class);
    $mockResolver->shouldReceive('requireForStudent')->andReturn('HCM');
    app()->instance(DngCampusCodeResolver::class, $mockResolver);

    $action = app(CreateRetakeCourseChargeAction::class);
    $result = $action->handle(['registration_id' => $reg->id]);

    // Registration remains payment_pending (no status change for this path)
    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);

    // Pivot row created
    $this->assertDatabaseHas('dng_payment_request_charges', [
        'dng_payment_request_id' => $fakeDngRequest2->id,
        'finance_charge_id' => $charge->id,
        'amount' => 5000000,
    ]);
});
