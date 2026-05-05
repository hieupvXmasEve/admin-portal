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
use App\Modules\Finance\Actions\CreateRetakeCourseChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
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

    // Mock DNG services
    $mockDngService = Mockery::mock(DngPaymentService::class);
    $mockDngService->shouldReceive('createAndPush')->andReturn(new DngPaymentRequest);
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

it('rejects charge creation for non-approved registration', function () {
    $reg = createChargeTestRegistration(['status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING]);

    $action = app(CreateRetakeCourseChargeAction::class);
    $action->handle([
        'registration_id' => $reg->id,
    ]);
})->throws(ValidationException::class);
