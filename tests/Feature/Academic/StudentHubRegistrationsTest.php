<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Modules\Academic\Progression\Queries\GetStudentRegistrationsQuery;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

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

    $data = app(GetStudentRegistrationsQuery::class)->handle((int) $student->id, []);

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

    $query = app(GetStudentRegistrationsQuery::class);

    // No filter -> all three registrations.
    expect($query->handle((int) $student->id, [])['data'])->toHaveCount(3);

    // Semester filter -> only the two Fall registrations.
    expect($query->handle((int) $student->id, ['semester_id' => $fall->id])['data'])->toHaveCount(2);

    // Status filter -> only the single completed registration.
    $completed = $query->handle((int) $student->id, ['status' => 'completed'])['data'];
    expect($completed)->toHaveCount(1)
        ->and($completed->first()['registration_status'])->toBe('completed');

    // Retake filter -> only the single retake registration.
    expect($query->handle((int) $student->id, ['is_retake' => 'true'])['data'])->toHaveCount(1);

    // UI sentinel values leave the result unfiltered.
    expect($query->handle((int) $student->id, ['status' => 'all'])['data'])->toHaveCount(3)
        ->and($query->handle((int) $student->id, ['is_retake' => 'all'])['data'])->toHaveCount(3);
});

it('keeps one student registrations out of another student hub', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-ISO', 'name' => 'Fall 2026 Iso']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);
    $other = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    makeStudentRegistration($student, $semester);
    makeStudentRegistration($other, $semester);

    $data = app(GetStudentRegistrationsQuery::class)->handle((int) $student->id, []);

    expect($data['data'])->toHaveCount(1)
        ->and($data['summary']['total_registrations'])->toBe(1);
});

it('prefers finalized Transcript Entry outcomes while preserving legacy-only registration fields', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-OUTCOME', 'name' => 'Fall 2026 Outcome']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);
    $registration = makeStudentRegistration($student, $semester, ['registration_status' => 'completed']);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $registration->course_offering_id,
        'semester_id' => $semester->id,
        'unit_id' => $registration->courseOffering->unit_id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'final_percentage' => 55.0,
        'final_letter_grade' => 'D',
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'meets_attendance_requirement' => true,
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'attempt_number' => 1,
        'is_passed' => true,
    ]);
    TranscriptEntry::query()->create([
        'course_result_id' => 999001,
        'student_id' => $student->id,
        'course_offering_id' => $registration->course_offering_id,
        'semester_id' => $semester->id,
        'unit_id' => $registration->courseOffering->unit_id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'attempt_number' => 2,
        'final_percentage' => 82.0,
        'final_letter_grade' => 'B',
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'quality_points' => 9.0,
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
        'finalized_at' => now(),
    ]);

    $row = app(GetStudentRegistrationsQuery::class)->handle((int) $student->id)['data']->sole();

    expect((float) $row['final_percentage'])->toBe(82.0)
        ->and($row['final_grade'])->toBe('B')
        ->and($row['grade_status'])->toBe('final')
        ->and($row['completion_status'])->toBe('completed')
        ->and($row['meets_attendance_requirement'])->toBeTrue()
        ->and($row['attempt_number'])->toBe(2)
        ->and($row['is_retake'])->toBeTrue();
});

function actAsRegistrationsUser(): User
{
    $user = User::factory()->create();
    $campus = Campus::factory()->create();

    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn(['view_student_summary']);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    return $user;
}

it('renders filtered registrations through the permitted Hub route', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-HTTP', 'name' => 'Fall 2026 Http']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);
    makeStudentRegistration($student, $semester, ['registration_status' => 'registered']);
    makeStudentRegistration($student, $semester, ['registration_status' => 'completed']);

    actingAs(actAsRegistrationsUser())
        ->get(route('students.academic-summary.registrations', ['student' => $student, 'status' => 'completed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/AcademicSummary/Registrations')
            ->has('registrations.data', 1)
            ->where('filters.status', 'completed'));
});

it('validates pagination limits before reading registrations', function () {
    $student = Student::factory()->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    actingAs(actAsRegistrationsUser())
        ->get(route('students.academic-summary.registrations', ['student' => $student, 'per_page' => 101]))
        ->assertSessionHasErrors('per_page');
});

it('renders empty range metadata when the requested page is past the final page', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-PAGE', 'name' => 'Fall 2026 Page']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);
    makeStudentRegistration($student, $semester);

    actingAs(actAsRegistrationsUser())
        ->get(route('students.academic-summary.registrations', ['student' => $student, 'page' => 2, 'per_page' => 50]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registrations.data', 0)
            ->where('registrations.pagination.from', null)
            ->where('registrations.pagination.to', null));
});
