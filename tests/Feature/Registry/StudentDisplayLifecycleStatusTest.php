<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Academic\Progression\Queries\PreviewStudentDecisionBulkLinkQuery;
use App\Modules\Academic\Progression\Support\EloquentStudentDeferLifecycleReader;
use App\Shared\Contracts\StudentRegistry\StudentPortalProfileReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
});

function displayStudent(object $ctx, string $code, string $columnStatus): Student
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

function displayEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): ProgramEnrollment
{
    return ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
}

it('shows the projected status on the portal profile reader, not the stale column', function (): void {
    $student = displayStudent($this, 'DISP001', 'deferred');
    displayEnrollment($student, 'active', 'intake_course');

    $profile = app(StudentPortalProfileReader::class)->forStudent((int) $student->id);

    expect($profile->toArray()['info']['status'])->toBe('intake_course');
});

it('resolves PreviewStudentDecisionBulkLinkQuery with exactly one program_enrollments query', function (): void {
    $user = User::factory()->create();
    $decision = StudentDecision::create([
        'decision_name' => 'Test decision',
        'decision_number' => 'DEC-001',
        'decision_signer' => 'Registrar',
        'issued_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    $codes = [];
    foreach (range(1, 5) as $i) {
        $student = displayStudent($this, "DISPB{$i}", 'deferred');
        displayEnrollment($student, 'active', 'intake_course');
        $codes[] = $student->student_id;
    }

    $queryCount = 0;
    DB::listen(function ($query) use (&$queryCount): void {
        if (str_contains($query->sql, 'program_enrollments')) {
            $queryCount++;
        }
    });

    $result = app(PreviewStudentDecisionBulkLinkQuery::class)->handle($decision, $codes, StudentActionType::ACADEMIC_DEFER->value);

    expect($queryCount)->toBe(1)
        ->and(collect($result['students'])->pluck('student.status')->unique()->all())->toBe(['intake_course']);
});

it('resolves EloquentStudentDeferLifecycleReader::listDeferActions with exactly one program_enrollments query', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $student = displayStudent($this, "DISPD{$i}", 'deferred');
        displayEnrollment($student, 'active', 'intake_course');
        StudentActionLog::create([
            'student_id' => $student->id,
            'action_type' => StudentActionType::ACADEMIC_DEFER->value,
            'reason' => 'Test defer',
            'changed_by_user_id' => $user->id,
        ]);
    }

    $queryCount = 0;
    DB::listen(function ($query) use (&$queryCount): void {
        if (str_contains($query->sql, 'program_enrollments')) {
            $queryCount++;
        }
    });

    $summaries = app(EloquentStudentDeferLifecycleReader::class)->listDeferActions();

    expect($queryCount)->toBe(1)
        ->and(collect($summaries)->pluck('currentlyDeferred')->unique()->all())->toBe([false]);
});
