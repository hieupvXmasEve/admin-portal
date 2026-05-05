<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\AutoEnrollRetakeCourseAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAutoEnrollRegistration(array $overrides = []): array
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
        'max_capacity' => 50,
        'current_enrollment' => 10,
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

    $registration = CourseRetakeRegistration::create(array_merge([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ], $overrides));

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

    return compact('registration', 'charge', 'student', 'courseOffering');
}

it('auto-enrolls student when payment is confirmed', function () {
    ['registration' => $reg, 'charge' => $charge, 'courseOffering' => $offering] = createAutoEnrollRegistration();

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);

    $reg->refresh();
    $offering->refresh();

    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED);
    expect($reg->paid_at)->not->toBeNull();
    expect($reg->enrolled_at)->not->toBeNull();
    expect($reg->course_registration_id)->not->toBeNull();
    expect($offering->current_enrollment)->toBe(11);

    // Verify CourseRegistration was created
    $courseReg = CourseRegistration::find($reg->course_registration_id);
    expect($courseReg)->not->toBeNull();
    expect($courseReg->is_retake)->toBeTrue();
    expect($courseReg->registration_method)->toBe('admin_override');
    expect($courseReg->registration_status)->toBe('confirmed');
});

it('skips non-retake-course charges', function () {
    ['charge' => $charge] = createAutoEnrollRegistration();

    // Change source_type to something else
    $charge->update(['source_type' => 'App\\Models\\Student']);

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);

    // No change expected
    $reg = CourseRetakeRegistration::first();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('skips registration not at payment_pending status', function () {
    ['registration' => $reg, 'charge' => $charge] = createAutoEnrollRegistration();

    // Manually set to approved (edge case: shouldn't happen but guard against it)
    $reg->update(['status' => CourseRetakeRegistration::STATUS_APPROVED]);

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_APPROVED);
});

it('enrolls even when course offering is full (admin override)', function () {
    ['registration' => $reg, 'charge' => $charge, 'courseOffering' => $offering] = createAutoEnrollRegistration();

    $offering->update([
        'max_capacity' => 10,
        'current_enrollment' => 10,
    ]);

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);

    $reg->refresh();
    $offering->refresh();

    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED);
    expect($offering->current_enrollment)->toBe(11);
});
