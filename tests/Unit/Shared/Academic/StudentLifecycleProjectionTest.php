<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Support\Academic\StudentLifecycleProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
});

function makeEnrolledStudent(object $ctx, string $code, string $columnStatus): Student
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

function makePrimaryEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): ProgramEnrollment
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

it('falls back to the legacy column when no enrollment was ever materialized', function (): void {
    $student = makeEnrolledStudent($this, 'PRJ001', 'deferred');

    $status = Student::query()
        ->whereKey($student->id)
        ->whereRaw(StudentLifecycleProjection::caseExpression().' = ?', ['deferred'])
        ->exists();

    expect($status)->toBeTrue();
});

it('projects the primary enrollment status over the drifted legacy column', function (): void {
    $student = makeEnrolledStudent($this, 'PRJ002', 'intake_pre_uni_gc');
    makePrimaryEnrollment($student, 'active', 'intake_course');

    $matchesProjection = Student::query()
        ->whereKey($student->id)
        ->whereRaw(StudentLifecycleProjection::caseExpression().' = ?', ['intake_course'])
        ->exists();
    $matchesLegacyColumn = Student::query()
        ->whereKey($student->id)
        ->whereRaw(StudentLifecycleProjection::caseExpression().' = ?', ['intake_pre_uni_gc'])
        ->exists();

    expect($matchesProjection)->toBeTrue()
        ->and($matchesLegacyColumn)->toBeFalse();
});

it('collapses a withdrawn enrollment to dropout, never dropout_transfer', function (): void {
    $student = makeEnrolledStudent($this, 'PRJ003', 'intake_course');
    makePrimaryEnrollment($student, 'withdrawn');

    $matches = Student::query()
        ->whereKey($student->id)
        ->whereRaw(StudentLifecycleProjection::caseExpression().' = ?', ['dropout'])
        ->exists();

    expect($matches)->toBeTrue();
});

it('binding A2 decision: among multiple is_primary rows, the highest id wins', function (): void {
    $student = makeEnrolledStudent($this, 'PRJ004', 'intake_course');
    $earlier = makePrimaryEnrollment($student, 'deferred');
    $latest = makePrimaryEnrollment($student, 'dropout');

    expect($latest->id)->toBeGreaterThan($earlier->id);

    $matchesLatest = Student::query()
        ->whereKey($student->id)
        ->whereRaw(StudentLifecycleProjection::caseExpression().' = ?', ['dropout'])
        ->exists();
    $matchesEarlier = Student::query()
        ->whereKey($student->id)
        ->whereRaw(StudentLifecycleProjection::caseExpression().' = ?', ['deferred'])
        ->exists();

    expect($matchesLatest)->toBeTrue()
        ->and($matchesEarlier)->toBeFalse();
});

it('whereStatusIn filters a query builder by the projected status', function (): void {
    $matching = makeEnrolledStudent($this, 'PRJ005', 'deferred');
    makePrimaryEnrollment($matching, 'active', 'intake_course');
    $nonMatching = makeEnrolledStudent($this, 'PRJ006', 'deferred');

    $ids = StudentLifecycleProjection::whereStatusIn(Student::query(), ['intake_course'])
        ->pluck('id')
        ->all();

    expect($ids)->toContain($matching->id)
        ->not->toContain($nonMatching->id);
});
