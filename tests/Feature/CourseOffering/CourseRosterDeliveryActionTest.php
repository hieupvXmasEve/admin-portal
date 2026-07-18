<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\EnrollStudentInCourseOfferingAction;
use App\Modules\Academic\Delivery\Actions\MoveStudentBetweenCourseOfferingSectionsAction;
use App\Modules\Academic\Delivery\Actions\RemoveStudentFromCourseOfferingAction;
use App\Shared\Contracts\Academic\CourseRosterReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('enrols a student through Delivery and closes an offering when its final seat is taken', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['credit_points' => 4]);
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 1,
        'current_enrollment' => 0,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);

    $registration = EnrollStudentInCourseOfferingAction::run([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'registration_status' => 'confirmed',
        'registration_method' => 'admin_override',
    ]);

    expect($registration->student_id)->toBe($student->id)
        ->and($registration->course_offering_id)->toBe($offering->id)
        ->and((float) $registration->credit_hours)->toBe(4.0)
        ->and($offering->fresh()->current_enrollment)->toBe(1)
        ->and($offering->fresh()->enrollment_status)->toBe('closed');
});

it('rejects duplicate registrations without consuming another seat', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'max_capacity' => 2,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    expect(fn (): mixed => EnrollStudentInCourseOfferingAction::run([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'registration_status' => 'confirmed',
        'registration_method' => 'admin_override',
    ]))->toThrow(ValidationException::class);

    expect($offering->fresh()->current_enrollment)->toBe(1)
        ->and(CourseRegistration::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('moves a roster member between sections and rolls back when the target is full', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $source = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
    ]);
    $target = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 1,
        'current_enrollment' => 1,
        'enrollment_status' => 'closed',
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $source->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    expect(fn (): mixed => MoveStudentBetweenCourseOfferingSectionsAction::run([
        'student_id' => $student->id,
        'target_course_offering_id' => $target->id,
    ]))->toThrow(ValidationException::class);

    expect($registration->fresh()->course_offering_id)->toBe($source->id)
        ->and($source->fresh()->current_enrollment)->toBe(1)
        ->and($target->fresh()->current_enrollment)->toBe(1);
});

it('counts a forced section move in the full target offering', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $source = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
    ]);
    $target = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 1,
        'current_enrollment' => 1,
        'enrollment_status' => 'closed',
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $source->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    MoveStudentBetweenCourseOfferingSectionsAction::run([
        'student_id' => $student->id,
        'target_course_offering_id' => $target->id,
        'force_move' => true,
    ]);

    expect($source->fresh()->current_enrollment)->toBe(0)
        ->and($target->fresh()->current_enrollment)->toBe(2);
});

it('moves dependent Delivery evidence with the roster member atomically', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $source = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
    ]);
    $target = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 2,
        'current_enrollment' => 0,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $source->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $source->id,
    ]);

    MoveStudentBetweenCourseOfferingSectionsAction::run([
        'student_id' => $student->id,
        'target_course_offering_id' => $target->id,
    ]);

    expect($registration->fresh()->course_offering_id)->toBe($target->id)
        ->and($record->fresh()->course_offering_id)->toBe($target->id)
        ->and($source->fresh()->current_enrollment)->toBe(0)
        ->and($target->fresh()->current_enrollment)->toBe(1);
});

it('removes a roster member through Delivery and releases their counted seat', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'max_capacity' => 2,
        'current_enrollment' => 1,
        'enrollment_status' => 'waitlist_only',
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    RemoveStudentFromCourseOfferingAction::run($registration->id);

    expect(CourseRegistration::query()->find($registration->id))->toBeNull()
        ->and($offering->fresh()->current_enrollment)->toBe(0)
        ->and($offering->fresh()->enrollment_status)->toBe('open');
});

it('uses the Delivery roster reader for active members in registration order', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
    ]);
    $activeStudent = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $deferredStudent = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'status' => 'deferred',
    ]);
    foreach ([$activeStudent, $deferredStudent] as $student) {
        CourseRegistration::query()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'credit_hours' => 3,
        ]);
    }

    expect(app(CourseRosterReader::class)->activeStudentIds($offering->id))->toBe([$activeStudent->id]);
});
