<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Services\PermissionService;

const BATCH_STUDIO_CSRF = 'batch-studio-test-csrf';

/**
 * Grant a user a fixed set of finance permissions for the active campus and
 * bind the campus context the way HandleInertiaRequests + finance controllers expect.
 *
 * @param  string[]  $permissions
 */
function grantFinance(User $user, array $permissions, Campus $campus): void
{
    session(financeWebSession($campus));
    app()->singleton('campus', fn () => $campus);

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->instance(PermissionService::class, $mock);
}

/**
 * @return array{current_campus_id: int, _token: string}
 */
function financeWebSession(Campus $campus): array
{
    return [
        'current_campus_id' => $campus->id,
        '_token' => BATCH_STUDIO_CSRF,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function financePostPayload(array $payload): array
{
    return array_merge(['_token' => BATCH_STUDIO_CSRF], $payload);
}

function makeBatchStartedStudent(Campus $campus, Semester $targetSemester, string $studentCode): Student
{
    $intakeSemester = Semester::factory()->create([
        'start_date' => $targetSemester->start_date->copy()->subMonths(4),
        'end_date' => $targetSemester->start_date->copy()->subMonth(),
    ]);

    return Student::factory()->forCampus($campus)->create([
        'student_id' => $studentCode,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $intakeSemester->id,
    ]);
}

function makeBatchHpStudent(Campus $campus, Semester $targetSemester, string $studentCode, string $status = 'intake_course'): Student
{
    $program = Program::factory()->create();
    $intakeSemester = Semester::factory()->create([
        'start_date' => $targetSemester->start_date->copy()->subMonths(4),
        'end_date' => $targetSemester->start_date->copy()->subMonth(),
    ]);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($intakeSemester)
        ->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $studentCode,
            'status' => $status,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $intakeSemester->id,
            'intake_course' => (string) $intakeSemester->id,
            'intake_major' => $targetSemester->id,
        ]);

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $intakeSemester->id,
        'total_amount' => 45000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => $targetSemester->start_date->copy()->addDays(14)->toDateString(),
    ]);

    return $student;
}

function seedBatchActiveCharge(Student $student, Semester $semester, string $chargeType): FinanceCharge
{
    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $chargeType,
        'amount' => 100000,
        'description' => 'Existing charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}
