<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Actions\UpdateProgramEnrollmentLifecycleAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Services\StudentStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('materializes legacy program and lifecycle facts once with provenance', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake_semester_id' => $semester->id,
        'intake_gc' => $semester->id,
        'intake' => 1,
        'status' => 'intake_pre_uni_gc',
        'academic_status' => 'active',
    ]);

    $first = MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
    $second = MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);

    expect($first->id)->toBe($second->id)
        ->and(ProgramEnrollment::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and($second->program_id)->toBe($student->program_id)
        ->and($second->curriculum_version_id)->toBe($student->curriculum_version_id)
        ->and($second->intake_semester_id)->toBe($semester->id)
        ->and($second->enrollment_status)->toBe('active')
        ->and($second->study_stage)->toBe('intake_pre_uni_gc')
        ->and($second->is_primary)->toBeTrue()
        ->and($second->source_type)->toBe('legacy_students')
        ->and($second->source_id)->toBe($student->id)
        ->and($second->source_snapshot['status'])->toBe('intake_pre_uni_gc');
});

it('keeps historical enrollments queryable while enforcing one primary active enrollment', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'status' => 'intake_course',
        'academic_status' => 'active',
    ]);
    $historical = new ProgramEnrollment([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $semester->id,
        'enrollment_status' => 'active',
        'is_primary' => true,
        'source_type' => 'legacy_import',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
    $historical->save();

    $current = MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);

    expect($historical->fresh()->is_primary)->toBeFalse()
        ->and($historical->fresh()->primary_active_student_id)->toBeNull()
        ->and($current->isPrimaryActive())->toBeTrue()
        ->and(ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_primary', true)
            ->where('enrollment_status', 'active')
            ->count())->toBe(1)
        ->and(ProgramEnrollment::query()->whereKey($historical->id)->exists())->toBeTrue();
});

it('rejects a second primary active enrollment at the database boundary', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'status' => 'intake_course',
        'academic_status' => 'active',
    ]);

    MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);

    expect(function () use ($student, $semester): void {
        ProgramEnrollment::query()->create([
            'student_id' => $student->id,
            'program_id' => $student->program_id,
            'curriculum_version_id' => $student->curriculum_version_id,
            'intake_semester_id' => $semester->id,
            'enrollment_status' => 'active',
            'is_primary' => true,
            'source_type' => 'manual_test',
            'source_id' => $student->id,
            'source_snapshot' => [],
            'materialized_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('changes enrollment status and study stage independently through Progression', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'status' => 'intake_course',
        'academic_status' => 'active',
    ]);
    $user = User::factory()->create();

    MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
    app(StudentStatusService::class)->updateStatus($student, 'suspended', 'Academic review', $user);
    $stageUpdated = UpdateProgramEnrollmentLifecycleAction::run([
        'student_id' => $student->id,
        'study_stage' => 'intake_major',
    ]);

    expect($stageUpdated->enrollment_status)->toBe('suspended')
        ->and($stageUpdated->study_stage)->toBe('intake_major')
        ->and($student->fresh()->academic_status)->toBe('suspended');
});

it('backfills the same Student idempotently through the academic command', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'status' => 'deferred',
        'academic_status' => 'active',
    ]);

    $this->artisan('academic:backfill-program-enrollments', ['--student-id' => $student->id])
        ->expectsOutput('Program enrollment materialization complete: 1 created, 0 refreshed.')
        ->assertSuccessful();
    $this->artisan('academic:backfill-program-enrollments', ['--student-id' => $student->id])
        ->expectsOutput('Program enrollment materialization complete: 0 created, 1 refreshed.')
        ->assertSuccessful();

    expect(ProgramEnrollment::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and(ProgramEnrollment::query()->sole()->enrollment_status)->toBe('deferred')
        ->and(ProgramEnrollment::query()->sole()->study_stage)->toBeNull();
});
