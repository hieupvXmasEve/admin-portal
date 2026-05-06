<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\UnitPrerequisiteCondition;
use App\Models\UnitPrerequisiteGroup;
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

it('creates a retake course registration with auto charge', function () {
    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result)->toBeInstanceOf(CourseRetakeRegistration::class);
    // Registration auto-transitions to payment_pending after charge creation
    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($result->student_id)->toBe($this->student->id);
    expect($result->unit_id)->toBe($this->courseOffering->unit_id);
    expect($result->approved_by_user_id)->toBe($this->user->id);
    expect($result->approved_at)->not->toBeNull();
    expect($result->finance_charge_id)->not->toBeNull();

    // Verify charge was created
    $charge = \App\Models\FinanceCharge::find($result->finance_charge_id);
    expect($charge)->not->toBeNull();
    expect($charge->charge_type)->toBe(\App\Models\FinanceCharge::TYPE_RETAKE_FEE);
    expect($charge->status)->toBe(\App\Models\FinanceCharge::STATUS_ACTIVE);
    expect((float) $charge->amount)->toBe((float) $result->retake_fee);

    // Verify invoice line was created
    $line = \App\Models\InvoiceLine::where('charge_id', $charge->id)->first();
    expect($line)->not->toBeNull();
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
    // Create and cancel first registration (auto-creates charge at payment_pending)
    $first = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // Use full cancel action to properly void charge + cancel registration
    \App\Modules\Academic\Actions\CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $first->id,
        'reason' => 'Test cancellation',
    ]);

    // Should succeed because previous was cancelled (terminal)
    $second = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // Second registration also auto-creates charge
    expect($second->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($second->finance_charge_id)->not->toBeNull();

    // Verify: 1 void charge (from cancelled) + 1 active charge (from new registration)
    $charges = \App\Models\FinanceCharge::where('student_id', $this->student->id)
        ->where('source_type', \App\Models\CourseRetakeRegistration::class)
        ->get();
    expect($charges->where('status', 'active')->count())->toBe(1);
    expect($charges->where('status', 'void')->count())->toBe(1);
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

it('rejects retake when student has not passed prerequisite unit', function () {
    $prereqUnit = Unit::factory()->create(['retake_fee' => 500000]);
    $targetUnit = $this->courseOffering->unit;

    // Create prerequisite group: targetUnit requires prereqUnit
    $group = UnitPrerequisiteGroup::create([
        'unit_id' => $targetUnit->id,
        'logic_operator' => 'AND',
        'description' => 'Must pass prereq unit first',
    ]);
    UnitPrerequisiteCondition::create([
        'group_id' => $group->id,
        'type' => 'prerequisite',
        'required_unit_id' => $prereqUnit->id,
    ]);

    // Student has NOT completed prereqUnit (no academic_record with completion_status=completed)

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $targetUnit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('allows retake when student has passed prerequisite unit', function () {
    $prereqUnit = Unit::factory()->create(['retake_fee' => 500000]);
    $targetUnit = $this->courseOffering->unit;

    // Create prerequisite group: targetUnit requires prereqUnit
    $group = UnitPrerequisiteGroup::create([
        'unit_id' => $targetUnit->id,
        'logic_operator' => 'AND',
        'description' => 'Must pass prereq unit first',
    ]);
    UnitPrerequisiteCondition::create([
        'group_id' => $group->id,
        'type' => 'prerequisite',
        'required_unit_id' => $prereqUnit->id,
    ]);

    // Student HAS completed prereqUnit — create a course offering for the prereq unit
    $prereqOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $prereqUnit->id,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $prereqUnit->id,
        'course_offering_id' => $prereqOffering->id,
        'completion_status' => 'completed',
        'is_passed' => true,
    ]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $targetUnit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result)->toBeInstanceOf(CourseRetakeRegistration::class);
    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});
