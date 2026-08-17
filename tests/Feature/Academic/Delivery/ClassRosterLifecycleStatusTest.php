<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
});

function rosterStudent(object $ctx, string $code, string $columnStatus): Student
{
    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'status' => $columnStatus,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();
}

function rosterEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): ProgramEnrollment
{
    static $sequence = 0;
    $sequence++;

    return ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => 'test_program_enrollment',
        'source_id' => ($student->id * 1000) + $sequence,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
}

function rosterRegistration(Student $student, CourseOffering $offering, Semester $semester): CourseRegistration
{
    return CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);
}

function rosterOffering(object $ctx): CourseOffering
{
    $unit = Unit::factory()->create(['unit_type' => 'general']);

    return CourseOffering::factory()->create([
        'campus_id' => $ctx->campus->id,
        'semester_id' => $ctx->semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 0,
        'max_capacity' => 50,
        'enrollment_status' => 'open',
        'course_status' => 'not_started',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
}

it('shows a drifted student as active in the class roster', function (): void {
    $offering = rosterOffering($this);
    $student = rosterStudent($this, 'ROSTER001', 'deferred');
    rosterEnrollment($student, 'active', 'intake_course');
    rosterRegistration($student, $offering, $this->semester);

    expect($student->fresh()->isClassRosterActive())->toBeTrue()
        ->and(Student::classRosterActive()->whereKey($student->id)->exists())->toBeTrue();
});

it('keeps the legacy verdict for a no-enrollment student', function (): void {
    $student = rosterStudent($this, 'ROSTER002', 'dropout');

    expect($student->fresh()->isClassRosterActive())->toBeFalse()
        ->and(Student::classRosterActive()->whereKey($student->id)->exists())->toBeFalse();
});

it('returns the projected classRosterStatus, not the stale column', function (): void {
    $offering = rosterOffering($this);
    $student = rosterStudent($this, 'ROSTER003', 'deferred');
    rosterEnrollment($student, 'withdrawn');
    $registration = rosterRegistration($student, $offering, $this->semester);

    expect($registration->classRosterStatus())->toBe('dropout')
        ->and($registration->classRosterStatusLabel())->toBe('Dropout');
});

it('counts an active class roster of 20 students with exactly one program_enrollments query', function (): void {
    $offering = rosterOffering($this);

    foreach (range(1, 20) as $i) {
        $student = rosterStudent($this, "ROSTERB{$i}", 'deferred');
        rosterEnrollment($student, 'active', 'intake_course');
        rosterRegistration($student, $offering, $this->semester);
    }

    $queryCount = 0;
    DB::listen(function ($query) use (&$queryCount): void {
        if (str_contains($query->sql, 'program_enrollments')) {
            $queryCount++;
        }
    });

    $count = $offering->fresh()->activeClassRosterEnrollmentCount();

    expect($count)->toBe(20)
        ->and($queryCount)->toBe(1);
});

it('binding A2/C5 decision: highest id wins for both the PHP relation and the SQL twin', function (): void {
    $student = rosterStudent($this, 'ROSTER004', 'intake_course');
    rosterEnrollment($student, 'deferred');
    $latest = rosterEnrollment($student, 'active', 'intake_pre_uni_gc');

    $fresh = $student->fresh();

    expect($fresh->primaryEnrollment?->id)->toBe($latest->id)
        ->and($fresh->lifecycleStatus())->toBe('intake_pre_uni_gc')
        ->and(Student::query()->whereKey($student->id)->active()->exists())->toBeTrue();
});
