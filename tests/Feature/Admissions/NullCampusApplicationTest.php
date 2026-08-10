<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Admissions\Queries\ListApplicationsQuery;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const NULL_CAMPUS_CSRF = 'null-campus-application-csrf';

beforeEach(function () {
    Cache::flush();
    session(['_token' => NULL_CAMPUS_CSRF]);
});

function grantPermission(User $user, ?Campus $campus, string $permissionCode): void
{
    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode]
    );

    $role = Role::factory()->create(['code' => 'null_campus_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);

    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus?->id,
        'role_id' => $role->id,
    ]);
}

it('accepts null campus_code and email on student_applications', function () {
    $application = StudentApplication::factory()->pending()->withoutCampus()->create([
        'email' => null,
        'student_code' => 'NC0000001',
    ]);

    expect($application->fresh())
        ->campus_code->toBeNull()
        ->email->toBeNull();
});

it('allows two applications to share the same email once the unique index is dropped', function () {
    StudentApplication::factory()->pending()->create([
        'email' => 'shared@example.test',
        'student_code' => 'NC0000002',
    ]);

    $second = StudentApplication::factory()->pending()->create([
        'email' => 'shared@example.test',
        'student_code' => 'NC0000003',
    ]);

    expect($second->exists)->toBeTrue();
});

it('excludes a null-campus application from a campus-scoped list and includes it in the all-campus list', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->forCampus($campus->code)->create(['student_code' => 'NC0000004']);
    $nullCampusApplication = StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'NC0000005']);

    $filters = ['search' => null, 'status' => null, 'intake' => null, 'per_page' => 15, 'sort' => 'created_at', 'direction' => 'desc'];

    $scoped = app(ListApplicationsQuery::class)->handle($filters, $campus->code);
    $allCampus = app(ListApplicationsQuery::class)->handle($filters, null);

    expect(collect($scoped->items())->pluck('id'))->not->toContain($nullCampusApplication->id)
        ->and(collect($allCampus->items())->pluck('id'))->toContain($nullCampusApplication->id);
});

it('lets a staff member edit and save a null-campus application', function () {
    $campus = Campus::factory()->create();
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    grantPermission($staff, $campus, 'edit_student_application');

    $application = StudentApplication::factory()->pending()->withoutCampus()->create([
        'full_name' => 'Before Edit',
        'phone' => '0900000000',
        'email' => 'edit-null-campus@example.test',
        'student_code' => 'NC0000006',
    ]);

    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', NULL_CAMPUS_CSRF)
        ->put(route('student-applications.update', $application), [
            'full_name' => 'After Edit',
            'phone' => '0900000000',
            'email' => 'edit-null-campus@example.test',
            'student_code' => 'NC0000006',
        ]);

    $response->assertRedirect();
    expect($application->fresh())
        ->full_name->toBe('After Edit')
        ->campus_code->toBeNull();
});

it('lets a staff member with permission at their current campus reject a null-campus application', function () {
    $campus = Campus::factory()->create();
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    grantPermission($staff, $campus, 'reject_student_application');

    $application = StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'NC0000007']);

    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', NULL_CAMPUS_CSRF)
        ->post(route('student-applications.reject', $application), [
            'rejected_reason' => 'Duplicate CRM record',
        ]);

    $response->assertRedirect();
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_REJECTED);
});

it('authorizes revoke (policy-level) for a staff member with permission at their current campus on a null-campus application', function () {
    $campus = Campus::factory()->create();
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    grantPermission($staff, $campus, 'revoke_student_application');

    // Policy checks only $application->campus?->id, so an enrolled-status row
    // with no linked Student is enough here — avoids Student::factory()'s
    // unrelated `intake` requirement.
    $application = StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'NC0000008']);
    $application->forceFill(['status' => StudentApplication::STATUS_ENROLLED])->save();

    session(['current_campus_id' => $campus->id]);

    expect(Gate::forUser($staff)->allows('revoke', $application))->toBeTrue();
});

it('still denies approving a null-campus application even with permission at the current campus', function () {
    $campus = Campus::factory()->create();
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    grantPermission($staff, $campus, 'approve_student_application');

    $application = StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'NC0000009']);

    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', NULL_CAMPUS_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ]);

    $response->assertForbidden();
});

it('rejects a duplicate application_academic_scores subject for the same application', function () {
    $application = StudentApplication::factory()->pending()->create(['student_code' => 'NC0000010']);

    \App\Modules\Admissions\Models\ApplicationAcademicScore::query()->create([
        'student_application_id' => $application->id,
        'subject_code' => 'toan',
        'score' => 8.5,
        'source' => 'school_report',
    ]);

    expect(fn () => \App\Modules\Admissions\Models\ApplicationAcademicScore::query()->create([
        'student_application_id' => $application->id,
        'subject_code' => 'toan',
        'score' => 9.0,
        'source' => 'national_exam',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('allows two guardians with the same relationship label on one application (manual entry, e.g. step-parent)', function () {
    $application = StudentApplication::factory()->pending()->create(['student_code' => 'NC0000011']);

    \App\Models\ApplicationGuardian::query()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Father One',
        'relationship' => 'father',
    ]);

    $second = \App\Models\ApplicationGuardian::query()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Father Two',
        'relationship' => 'father',
    ]);

    expect($second->exists)->toBeTrue();
});

it('seeds major crm_value_mappings from IntendedProgramNormalizer::LABEL_TO_CODE', function () {
    foreach (\App\Services\Admissions\IntendedProgramNormalizer::LABEL_TO_CODE as $label => $code) {
        $mapping = \App\Modules\Admissions\Models\CrmValueMapping::query()
            ->where('kind', 'major')
            ->where('crm_value', $label)
            ->first();

        expect($mapping)->not->toBeNull()
            ->and($mapping->local_code)->toBe($code);
    }
});
