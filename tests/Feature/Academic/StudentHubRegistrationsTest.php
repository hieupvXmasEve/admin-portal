<?php

declare(strict_types=1);

use App\Actions\Student\GetStudentRegistrationsAction;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue 03 — Registrations read tab.
 *
 * Locks the Query-seam data contract the Hub UI relies on: each row carries
 * its course-offering reference (the student -> class bridge) and the
 * year / semester / status / retake filters narrow the result set.
 */

/**
 * Create a registration (and its offering) for the student in a semester.
 *
 * @param  array<string, mixed>  $overrides  CourseRegistration column overrides,
 *                                           plus optional `unit` / `section_code`.
 */
function makeStudentRegistration(Student $student, Semester $semester, array $overrides = []): CourseRegistration
{
    $unit = $overrides['unit'] ?? Unit::factory()->create();
    $sectionCode = $overrides['section_code'] ?? 'A';
    unset($overrides['unit'], $overrides['section_code']);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'section_code' => $sectionCode,
    ]);

    return CourseRegistration::query()->create(array_merge([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now()->subMonth(),
        'registration_method' => 'advisor',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
    ], $overrides));
}

it('exposes each registration course offering and section so the hub can link into the class', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-REG', 'name' => 'Fall 2026 Reg']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    $unit = Unit::factory()->create(['code' => 'AU101', 'name' => 'Intro to Hub']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'section_code' => 'B2',
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now()->subMonth(),
        'registration_method' => 'advisor',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
    ]);

    $data = app(GetStudentRegistrationsAction::class)->execute($student, []);

    expect($data['data'])->toHaveCount(1);

    $row = $data['data']->first();

    expect($row['course_offering_id'])->toBe($offering->id)
        ->and($row['section_code'])->toBe('B2')
        ->and($row['course_code'])->toBe('AU101')
        ->and($row['registration_status'])->toBe('registered');
});

it('narrows registrations by semester, status, and retake filters', function () {
    $campus = Campus::factory()->create();
    $fall = Semester::factory()->create(['code' => 'FALL2026-F', 'name' => 'Fall 2026 F']);
    $spring = Semester::factory()->create(['code' => 'SPR2027-S', 'name' => 'Spring 2027 S']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    makeStudentRegistration($student, $fall, ['registration_status' => 'registered', 'is_retake' => false]);
    makeStudentRegistration($student, $fall, ['registration_status' => 'completed', 'is_retake' => true]);
    makeStudentRegistration($student, $spring, ['registration_status' => 'registered', 'is_retake' => false]);

    $action = app(GetStudentRegistrationsAction::class);

    // No filter -> all three registrations.
    expect($action->execute($student, [])['data'])->toHaveCount(3);

    // Semester filter -> only the two Fall registrations.
    expect($action->execute($student, ['semester_id' => $fall->id])['data'])->toHaveCount(2);

    // Status filter -> only the single completed registration.
    $completed = $action->execute($student, ['status' => 'completed'])['data'];
    expect($completed)->toHaveCount(1)
        ->and($completed->first()['registration_status'])->toBe('completed');

    // Retake filter -> only the single retake registration.
    expect($action->execute($student, ['is_retake' => 'true'])['data'])->toHaveCount(1);
});

it('keeps one student registrations out of another student hub', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-ISO', 'name' => 'Fall 2026 Iso']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);
    $other = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    makeStudentRegistration($student, $semester);
    makeStudentRegistration($other, $semester);

    $data = app(GetStudentRegistrationsAction::class)->execute($student, []);

    expect($data['data'])->toHaveCount(1)
        ->and($data['summary']['total_registrations'])->toBe(1);
});
