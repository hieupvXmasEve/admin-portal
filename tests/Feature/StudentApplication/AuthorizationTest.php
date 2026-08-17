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
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const AUTHZ_CSRF = 'student-application-authz-csrf';

beforeEach(function () {
    // Permissions are resolved through the real, campus-scoped CampusPermissionReader
    // here (no mock). It caches per user+campus; RefreshDatabase resets ids each
    // test, so flush to avoid stale cross-test cache hits.
    Cache::flush();

    session([
        '_token' => AUTHZ_CSRF,
    ]);

    app(CrmMappingSettings::class)->setIntakeCohort(1);
});

function authzStaff(): User
{
    return User::factory()->create(['type' => UserType::STAFF]);
}

/**
 * Grant a single permission to a user, scoped to one campus, through the real
 * Permission → Role → CampusUserRole chain the platform uses.
 */
function grantPermissionAtCampus(User $user, Campus $campus, string $permissionCode): void
{
    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode]
    );

    $role = Role::factory()->create(['code' => 'authz_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);

    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}

function authzPendingApplication(Campus $campus, array $overrides = []): StudentApplication
{
    return StudentApplication::factory()->pending()->create(array_merge([
        'campus_code' => $campus->code,
        'intended_program' => 'IT',
        'intake' => 'FA25',
        'student_code' => 'S'.fake()->unique()->numerify('#######'),
    ], $overrides));
}

/**
 * Mapping fixtures so an approved application's CRM codes resolve to a Student.
 */
function authzApprovalMapping(): void
{
    $program = Program::factory()->create(['code' => 'IT']);
    $semester = Semester::factory()->create(['code' => 'FA25']);
    CurriculumVersion::factory()->forProgram($program)->create([
        'semester_id' => $semester->id,
        'version_code' => 'IT2025.v1',
    ]);
    Role::factory()->create(['code' => 'sinh_vien', 'name' => 'Sinh viên']);
}

it('lets a staff member with the permission at the application campus approve it', function () {
    $campus = Campus::factory()->create();
    authzApprovalMapping();

    $staff = authzStaff();
    grantPermissionAtCampus($staff, $campus, 'approve_student_application');

    $application = authzPendingApplication($campus, [
        'email' => 'authorized@example.com',
        'student_code' => 'S1234567',
    ]);

    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', AUTHZ_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ]);

    $response->assertRedirect();
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_ENROLLED);
});

it('forbids a staff member without the permission from approving', function () {
    $campus = Campus::factory()->create();
    $staff = authzStaff(); // no permissions granted
    $application = authzPendingApplication($campus);

    session(['current_campus_id' => $campus->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', AUTHZ_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('lets a staff member with the permission at the application campus reject it', function () {
    $campus = Campus::factory()->create();

    $staff = authzStaff();
    grantPermissionAtCampus($staff, $campus, 'reject_student_application');

    $application = authzPendingApplication($campus);

    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', AUTHZ_CSRF)
        ->post(route('student-applications.reject', $application), [
            'rejected_reason' => 'Incomplete dossier.',
        ]);

    $response->assertRedirect();
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_REJECTED);
});

it('forbids a staff member without the permission from rejecting', function () {
    $campus = Campus::factory()->create();
    $staff = authzStaff(); // no permissions granted
    $application = authzPendingApplication($campus);

    session(['current_campus_id' => $campus->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', AUTHZ_CSRF)
        ->post(route('student-applications.reject', $application), [
            'rejected_reason' => 'Should never apply.',
        ])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('forbids approving an application for a campus the staff member is not permitted at', function () {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();
    authzApprovalMapping();

    // Staff holds approve at campus A only; the application belongs to campus B.
    $staff = authzStaff();
    grantPermissionAtCampus($staff, $campusA, 'approve_student_application');

    $application = authzPendingApplication($campusB);

    session(['current_campus_id' => $campusA->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', AUTHZ_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('forbids rejecting an application for a campus the staff member is not permitted at', function () {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();

    $staff = authzStaff();
    grantPermissionAtCampus($staff, $campusA, 'reject_student_application');

    $application = authzPendingApplication($campusB);

    session(['current_campus_id' => $campusA->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', AUTHZ_CSRF)
        ->post(route('student-applications.reject', $application), [
            'rejected_reason' => 'Should never apply.',
        ])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

/*
 * Revoke is gated by the same campus-scoped policy now (so slice 04 can rely on
 * it), but its staff route/controller arrive in slice 04. These assert the
 * authorization decision directly at the policy seam.
 */

it('authorizes revoke for a staff member with the permission at the application campus', function () {
    $campus = Campus::factory()->create();
    $staff = authzStaff();
    grantPermissionAtCampus($staff, $campus, 'revoke_student_application');

    $application = authzPendingApplication($campus);

    expect(Gate::forUser($staff)->check('revoke', $application))->toBeTrue();
});

it('denies revoke for a staff member without the permission', function () {
    $campus = Campus::factory()->create();
    $staff = authzStaff(); // no permissions granted

    $application = authzPendingApplication($campus);

    expect(Gate::forUser($staff)->check('revoke', $application))->toBeFalse();
});

it('denies revoke for an application at a campus the staff member is not permitted at', function () {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();

    $staff = authzStaff();
    grantPermissionAtCampus($staff, $campusA, 'revoke_student_application');

    $application = authzPendingApplication($campusB);

    expect(Gate::forUser($staff)->check('revoke', $application))->toBeFalse();
});
