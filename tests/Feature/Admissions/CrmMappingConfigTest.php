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
use App\Modules\Admissions\Models\CrmValueMapping;
use App\Modules\Admissions\Queries\ListUnmappedCrmValuesQuery;
use App\Modules\Admissions\Services\CrmMappingResolver;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const CRM_MAPPING_CSRF = 'crm-mapping-config-csrf';

beforeEach(function () {
    Cache::flush();
    session(['_token' => CRM_MAPPING_CSRF]);
});

function crmMappingGrantPermission(User $user, ?Campus $campus, string $permissionCode): void
{
    $permission = Permission::firstOrCreate(['code' => $permissionCode], ['name' => $permissionCode]);
    $role = Role::factory()->create(['code' => 'crm_map_'.Str::lower(Str::random(10))]);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $campus?->id, 'role_id' => $role->id]);
}

it('returns 403 on index for a user without manage_crm_value_mapping', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    session(['current_campus_id' => Campus::factory()->create()->id]);

    $this->actingAs($staff)->get(route('student-applications.crm-mappings.index'))->assertForbidden();
});

it('returns 403 on store for a user without manage_crm_value_mapping', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    session(['current_campus_id' => Campus::factory()->create()->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_MAPPING_CSRF)
        ->post(route('student-applications.crm-mappings.store'), ['kind' => 'campus', 'crm_value' => 'X', 'local_code' => 'X'])
        ->assertForbidden();
});

it('GET /student-applications/crm-mappings resolves to the mapping screen, not the show route', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    $campus = Campus::factory()->create();
    crmMappingGrantPermission($staff, $campus, 'manage_crm_value_mapping');
    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)->get(route('student-applications.crm-mappings.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('StudentApplications/CrmMappings'));
});

it('groups unmapped values by kind with the correct affected count', function () {
    StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'C1', 'crm_campus' => 'Unmapped X']);
    StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'C2', 'crm_campus' => 'Unmapped X']);
    StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'C3', 'crm_campus' => 'Unmapped X']);

    $rows = app(ListUnmappedCrmValuesQuery::class)->handle(null);

    $row = collect($rows)->firstWhere('crm_value', 'Unmapped X');
    expect($row)->not->toBeNull()
        ->and($row['affected_count'])->toBe(3)
        ->and($row['kind'])->toBe('campus');
});

it('excludes seeded major labels from the unmapped list', function () {
    StudentApplication::factory()->pending()->create(['student_code' => 'M1', 'crm_major' => 'Trí tuệ nhân tạo']);

    $rows = app(ListUnmappedCrmValuesQuery::class)->handle(null);

    expect(collect($rows)->firstWhere('crm_value', 'Trí tuệ nhân tạo'))->toBeNull();
});

it('a campus-scoped actor never sees unmapped campus values (unattributable to any campus)', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->withoutCampus()->create(['student_code' => 'CS1', 'crm_campus' => 'Other Campus Value']);

    $rows = app(ListUnmappedCrmValuesQuery::class)->handle($campus->code);

    expect(collect($rows)->where('kind', 'campus'))->toBeEmpty();
});

it('a campus-scoped actor sees only their own campus unmapped major values', function () {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();
    StudentApplication::factory()->pending()->forCampus($campusA->code)->create(['student_code' => 'MA1', 'crm_major' => 'Major A']);
    StudentApplication::factory()->pending()->forCampus($campusB->code)->create(['student_code' => 'MB1', 'crm_major' => 'Major B']);

    $rowsForA = app(ListUnmappedCrmValuesQuery::class)->handle($campusA->code);

    expect(collect($rowsForA)->pluck('crm_value'))->toContain('Major A')
        ->and(collect($rowsForA)->pluck('crm_value'))->not->toContain('Major B');
});

it('saving a mapping fills campus_code on all affected pending applications with no re-sync', function () {
    $campus = Campus::factory()->create(['code' => 'HCM']);
    $applications = StudentApplication::factory()->pending()->withoutCampus()->count(3)->sequence(
        ['student_code' => 'BF1', 'crm_campus' => 'TP. Hồ Chí Minh'],
        ['student_code' => 'BF2', 'crm_campus' => 'TP. Hồ Chí Minh'],
        ['student_code' => 'BF3', 'crm_campus' => 'Other'],
    )->create();

    CrmValueMapping::query()->create(['kind' => 'campus', 'crm_value' => 'TP. Hồ Chí Minh', 'local_code' => 'HCM']);

    $result = app(CrmMappingResolver::class)->resolve();

    expect($result['skipped'])->toBeFalse()
        ->and($result['updated'])->toBe(2)
        ->and(StudentApplication::where('student_code', 'BF1')->value('campus_code'))->toBe('HCM')
        ->and(StudentApplication::where('student_code', 'BF2')->value('campus_code'))->toBe('HCM')
        ->and(StudentApplication::where('student_code', 'BF3')->value('campus_code'))->toBeNull();
});

it('never overwrites a manually set campus_code differing from the mapping', function () {
    Campus::factory()->create(['code' => 'HCM']);
    Campus::factory()->create(['code' => 'HAN']);
    StudentApplication::factory()->pending()->forCampus('HAN')->create(['student_code' => 'MANUAL1', 'crm_campus' => 'TP. Hồ Chí Minh']);

    CrmValueMapping::query()->create(['kind' => 'campus', 'crm_value' => 'TP. Hồ Chí Minh', 'local_code' => 'HCM']);

    app(CrmMappingResolver::class)->resolve();

    expect(StudentApplication::where('student_code', 'MANUAL1')->value('campus_code'))->toBe('HAN');
});

it('never backfills an enrolled or rejected application', function () {
    Campus::factory()->create(['code' => 'HCM']);
    $enrolled = StudentApplication::factory()->create(['student_code' => 'ENR1', 'status' => StudentApplication::STATUS_ENROLLED, 'campus_code' => null, 'crm_campus' => 'TP. Hồ Chí Minh']);

    CrmValueMapping::query()->create(['kind' => 'campus', 'crm_value' => 'TP. Hồ Chí Minh', 'local_code' => 'HCM']);

    app(CrmMappingResolver::class)->resolve();

    expect($enrolled->fresh()->campus_code)->toBeNull();
});

it('target intake is read/written through CrmMappingSettings alone', function () {
    Semester::factory()->create(['code' => 'FA25', 'name' => 'Fall 2025']);

    app(CrmMappingSettings::class)->setIntakeCode('FA25');

    expect(app(CrmMappingSettings::class)->getIntakeCode())->toBe('FA25')
        ->and(CrmValueMapping::query()->where('kind', 'intake')->where('crm_value', '__default__')->value('local_code'))->toBe('FA25');
});

it('backfill fills intake from the target-intake setting', function () {
    Semester::factory()->create(['code' => 'FA25']);
    app(CrmMappingSettings::class)->setIntakeCode('FA25');
    StudentApplication::factory()->pending()->create(['student_code' => 'INT1', 'intake' => null]);

    app(CrmMappingResolver::class)->resolve();

    expect(StudentApplication::where('student_code', 'INT1')->value('intake'))->toBe('FA25');
});

it('backfill refuses to run while a sync holds the lock and reports skipped', function () {
    $lock = Cache::lock(\App\Modules\Admissions\Services\CrmApplicationSyncService::LOCK_NAME, 30);
    $lock->get();

    try {
        $result = app(CrmMappingResolver::class)->resolve();
        expect($result['skipped'])->toBeTrue();
    } finally {
        $lock->release();
    }
});

it('accepts and validates a program code for a major mapping via the store endpoint', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    $campus = Campus::factory()->create();
    crmMappingGrantPermission($staff, $campus, 'manage_crm_value_mapping');
    session(['current_campus_id' => $campus->id]);
    Program::factory()->create(['code' => 'IT']);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_MAPPING_CSRF)
        ->post(route('student-applications.crm-mappings.store'), ['kind' => 'major', 'crm_value' => 'Some Major', 'local_code' => 'IT']);

    $response->assertRedirect();
    expect(CrmValueMapping::query()->where('kind', 'major')->where('crm_value', 'Some Major')->value('local_code'))->toBe('IT');
});

it('rejects a major mapping with a program code that does not exist', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    $campus = Campus::factory()->create();
    crmMappingGrantPermission($staff, $campus, 'manage_crm_value_mapping');
    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_MAPPING_CSRF)
        ->post(route('student-applications.crm-mappings.store'), ['kind' => 'major', 'crm_value' => 'Some Major', 'local_code' => 'NOPE']);

    $response->assertSessionHasErrors('local_code');
});

it('never grants manage_crm_value_mapping to a campus-scoped role in the seeder (M5)', function () {
    $this->seed(\Database\Seeders\InitialSetup\RoleAndPermissionSeeder::class);

    $permission = Permission::where('code', 'manage_crm_value_mapping')->first();
    $campusScopedRoleCodes = ['truong_phong', 'can_bo'];

    foreach ($campusScopedRoleCodes as $roleCode) {
        $role = Role::where('code', $roleCode)->first();
        expect(RolePermission::where('role_id', $role->id)->where('permission_id', $permission->id)->exists())->toBeFalse();
    }
});
