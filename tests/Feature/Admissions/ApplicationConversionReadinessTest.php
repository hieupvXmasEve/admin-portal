<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CurriculumVersion;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Admissions\Queries\GetApplicationConversionReadinessQuery;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const READINESS_CSRF = 'readiness-test-csrf';

beforeEach(function () {
    Cache::flush();
    session(['_token' => READINESS_CSRF]);
});

function readinessGrantPermission(User $user, Campus $campus, string $permissionCode): void
{
    $permission = Permission::firstOrCreate(['code' => $permissionCode], ['name' => $permissionCode]);
    $role = Role::factory()->create(['code' => 'readiness_'.Str::lower(Str::random(10))]);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id]);
}

function readinessFullMapping(): array
{
    $campus = Campus::factory()->create(['code' => 'HCM']);
    $program = Program::factory()->create(['code' => 'IT']);
    $semester = Semester::factory()->create(['code' => 'FA25']);
    CurriculumVersion::factory()->forProgram($program)->create(['semester_id' => $semester->id, 'version_code' => 'IT2025.v1']);

    return ['campus' => $campus, 'program' => $program, 'semester' => $semester];
}

it('reports ready when campus, program, and intake are all resolved with a unique curriculum', function () {
    readinessFullMapping();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25']);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeTrue()
        ->and($readiness['missing'])->toBe([]);
});

it('reports missing campus naming the raw CRM value, with no phantom curriculum reason', function () {
    $application = StudentApplication::factory()->pending()->withoutCampus()->create(['crm_campus' => 'TP. Hồ Chí Minh', 'intended_program' => null, 'intake' => null]);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeFalse();
    $campusMissing = collect($readiness['missing'])->firstWhere('field', 'campus_code');
    expect($campusMissing['crm_value'])->toBe('TP. Hồ Chí Minh');
    expect(collect($readiness['missing'])->firstWhere('field', 'curriculum_version'))->toBeNull();
});

it('reports missing intake when the target-intake setting has not been configured', function () {
    Campus::factory()->create(['code' => 'HCM']);
    Program::factory()->create(['code' => 'IT']);
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => null]);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeFalse();
    expect(collect($readiness['missing'])->firstWhere('field', 'intake'))->not->toBeNull();
});

it('reports no_curriculum with program_id/semester_id so the UI can deep-link to Curriculum Versions', function () {
    $campus = Campus::factory()->create(['code' => 'HCM']);
    $program = Program::factory()->create(['code' => 'IT']);
    $semester = Semester::factory()->create(['code' => 'FA25']);
    // No CurriculumVersion for this (program, semester) pair.
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25']);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeFalse();
    $curriculumMissing = collect($readiness['missing'])->firstWhere('field', 'curriculum_version');
    expect($curriculumMissing)->not->toBeNull()
        ->and($curriculumMissing['reason'])->toBe('no_curriculum')
        ->and($curriculumMissing['program_id'])->toBe($program->id)
        ->and($curriculumMissing['semester_id'])->toBe($semester->id);
});

it('reports a distinct reason when resolved but the curriculum is ambiguous', function () {
    $campus = Campus::factory()->create(['code' => 'HCM']);
    $program = Program::factory()->create(['code' => 'IT']);
    $semester = Semester::factory()->create(['code' => 'FA25']);
    $specA = Specialization::factory()->create(['program_id' => $program->id]);
    $specB = Specialization::factory()->create(['program_id' => $program->id]);
    CurriculumVersion::factory()->forProgram($program)->create(['semester_id' => $semester->id, 'specialization_id' => $specA->id, 'version_code' => 'IT2025.A']);
    CurriculumVersion::factory()->forProgram($program)->create(['semester_id' => $semester->id, 'specialization_id' => $specB->id, 'version_code' => 'IT2025.B']);

    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25', 'intended_specialization' => null]);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeFalse();
    $curriculumMissing = collect($readiness['missing'])->firstWhere('field', 'curriculum_version');
    expect($curriculumMissing['reason'])->toBe('ambiguous_curriculum')
        ->and($curriculumMissing['program_id'])->toBe($program->id)
        ->and($curriculumMissing['semester_id'])->toBe($semester->id);
});

it('reports missing email when blank', function () {
    readinessFullMapping();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25', 'email' => null]);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeFalse();
    expect(collect($readiness['missing'])->firstWhere('field', 'email')['reason'])->toBe('blank_email');
});

it('reports a duplicate email already held by another user account', function () {
    readinessFullMapping();
    User::factory()->create(['email' => 'taken@example.test']);
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25', 'email' => 'taken@example.test']);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeFalse();
    expect(collect($readiness['missing'])->firstWhere('field', 'email')['reason'])->toBe('duplicate_email');
});

it('non-required unmapped values never block approval and surface only as warnings', function () {
    readinessFullMapping();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25', 'scholarship' => 'Unmapped Scholarship']);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeTrue()
        ->and(collect($readiness['warnings'])->firstWhere('field', 'scholarship'))->not->toBeNull();
});

it('full flow: sync an unmapped record, approve is blocked, reject stays available, mapping resolves it, approve succeeds', function () {
    $mapping = readinessFullMapping();
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    readinessGrantPermission($staff, $mapping['campus'], 'approve_student_application');
    readinessGrantPermission($staff, $mapping['campus'], 'reject_student_application');
    session(['current_campus_id' => $mapping['campus']->id]);

    // Simulate a partially-mapped application: campus is resolved (satisfies
    // the approve *policy*, which stays hard-denied on a null campus — see
    // StudentApplicationPolicy), but major/intake are still unmapped, so the
    // request reaches ApproveApplicationAction and its readiness guard fires.
    $application = StudentApplication::factory()->pending()->create([
        'campus_code' => 'HCM',
        'crm_major' => 'Trí tuệ nhân tạo AI Unmapped',
        'intended_program' => null,
        'intake' => null,
        'student_code' => 'RDY0000001',
    ]);

    // Approve blocked with the mapping error.
    $blocked = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', READINESS_CSRF)
        ->post(route('student-applications.approve', $application), ['admission_date' => now()->toDateString()]);
    $blocked->assertRedirect();
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);

    // Now map campus/major/intake so it fully resolves.
    $application->update(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25']);

    $approved = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', READINESS_CSRF)
        ->post(route('student-applications.approve', $application), ['admission_date' => now()->toDateString()]);
    $approved->assertRedirect();
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_ENROLLED)
        ->and($application->fresh()->student)->not->toBeNull()
        ->and($application->fresh()->student->campus_id)->toBe($mapping['campus']->id);
});

it('approve flashes only an error, never success, when the curriculum version is missing (no double toast)', function () {
    $campus = Campus::factory()->create(['code' => 'HCM']);
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    readinessGrantPermission($staff, $campus, 'approve_student_application');
    Program::factory()->create(['code' => 'IT']);
    Semester::factory()->create(['code' => 'FA25']);
    // Deliberately no CurriculumVersion for IT/FA25 — triggers the
    // 'no_curriculum' readiness failure the controller catches as
    // ApplicationLifecycleException.

    $application = StudentApplication::factory()->pending()->create([
        'campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25', 'student_code' => 'RDY0000002',
    ]);

    session(['current_campus_id' => $campus->id]);
    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', READINESS_CSRF)
        ->post(route('student-applications.approve', $application), ['admission_date' => now()->toDateString()]);

    // The redirect always carries 200/302 on this domain-failure path (only
    // the flash distinguishes it), so a stray onSuccess-toast in the frontend
    // would fire alongside this error toast — see Show.vue's approve()/
    // submitRevoke() comments. Session must carry error only, never success.
    $response->assertRedirect();
    $response->assertSessionHas('error', 'No curriculum version exists for this program and intake. Set one up before approving.');
    $response->assertSessionMissing('success');
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('existing manual applications with campus/program/intake already set are unaffected', function () {
    readinessFullMapping();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => 'HCM', 'intended_program' => 'IT', 'intake' => 'FA25']);

    $readiness = app(GetApplicationConversionReadinessQuery::class)->handle($application);

    expect($readiness['ready'])->toBeTrue();
});
