<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\CreateRetakeCourseRegistrationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
    ]);

    $this->academicRecord = AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $this->courseOffering->unit_id,
        'course_offering_id' => $this->courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
});

it('creates a retake course registration successfully', function () {
    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result)->toBeInstanceOf(CourseRetakeRegistration::class);
    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_APPROVED);
    expect($result->student_id)->toBe($this->student->id);
    expect($result->unit_id)->toBe($this->courseOffering->unit_id);
    expect($result->approved_by_user_id)->toBe($this->user->id);
    expect($result->approved_at)->not->toBeNull();
});

it('calculates attempt_number from existing academic records', function () {
    // The beforeEach already created 1 academic record for this student+unit
    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // 1 existing academic record + 1 = attempt 2
    expect($result->attempt_number)->toBe(2);
});

it('rejects student not in intake_course status', function () {
    $this->student->update(['status' => 'active']);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('rejects duplicate active registration for same student+unit+semester', function () {
    // Create first registration
    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // Attempt duplicate
    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('allows registration after previous one was cancelled', function () {
    // Create and cancel first registration
    $first = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    $first->cancel($this->user->id, 'Test cancellation');

    // Should succeed because previous was cancelled (terminal)
    $second = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($second->status)->toBe(CourseRetakeRegistration::STATUS_APPROVED);
});

it('rejects registration when unit has zero retake_fee', function () {
    $unit = $this->academicRecord->unit;
    $unit->update(['retake_fee' => 0]);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('rejects registration when unit has null retake_fee', function () {
    $unit = $this->academicRecord->unit;
    $unit->update(['retake_fee' => null]);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('snapshots retake_fee from unit', function () {
    // Set retake fee on unit
    $unit = $this->academicRecord->unit;
    $unit->update(['retake_fee' => 7500000]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect((float) $result->retake_fee)->toBe(7500000.00);
});
