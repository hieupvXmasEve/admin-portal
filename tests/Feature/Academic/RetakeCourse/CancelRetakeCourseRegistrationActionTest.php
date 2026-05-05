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
use App\Modules\Academic\Actions\CancelRetakeCourseRegistrationAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCancelTestRegistration(string $status = 'approved', array $overrides = []): CourseRetakeRegistration
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
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => $status,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('cancels an approved registration', function () {
    $reg = createCancelTestRegistration('approved');

    $result = CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Student request',
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
    expect($result->cancellation_reason)->toBe('Student request');
    expect($result->cancelled_by_user_id)->toBe($this->user->id);
});

it('cancels a payment_pending registration and voids charge', function () {
    // Create charge
    $reg = createCancelTestRegistration('approved');
    $charge = FinanceCharge::create([
        'student_id' => $reg->student_id,
        'semester_id' => $reg->semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class,
        'source_id' => $reg->id,
    ]);

    $reg->update([
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'finance_charge_id' => $charge->id,
    ]);

    // Mock VoidFinanceChargeAction to avoid complex dependencies
    $mockVoid = Mockery::mock(VoidFinanceChargeAction::class);
    $mockVoid->shouldReceive('handle')
        ->once()
        ->with($charge->id, 'retake_course_cancelled', $this->user->id);
    app()->instance(VoidFinanceChargeAction::class, $mockVoid);

    $result = CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Fee issue',
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('throws when cancelling a paid registration', function () {
    $reg = createCancelTestRegistration('paid');

    CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Should fail',
    ]);
})->throws(RuntimeException::class);

it('throws when cancelling an enrolled registration', function () {
    $reg = createCancelTestRegistration('enrolled');

    CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $reg->id,
        'reason' => 'Should fail',
    ]);
})->throws(RuntimeException::class);
