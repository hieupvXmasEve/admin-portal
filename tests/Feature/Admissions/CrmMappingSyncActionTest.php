<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Admissions\Support\Crm\CrmIntegrationSettings;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const CRM_SYNC_ACTION_CSRF = 'crm-sync-action-csrf';

beforeEach(function () {
    Cache::flush();
    session(['_token' => CRM_SYNC_ACTION_CSRF]);
    config([
        'services.crm.login_url' => 'https://crm.test/api/login',
        'services.crm.data_url' => 'https://crm.test/api/ne',
        'services.crm.username' => 'u',
        'services.crm.password' => 'p',
        'services.crm.timeout' => 5,
    ]);
});

function grantCrmSyncPermission(User $user, Campus $campus): User
{
    $permission = Permission::firstOrCreate(['code' => 'manage_crm_value_mapping'], ['name' => 'manage_crm_value_mapping']);
    $role = Role::factory()->create(['code' => 'crm_sync_'.Str::lower(Str::random(10))]);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id]);

    return $user;
}

it('returns 403 on login without manage_crm_value_mapping', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    session(['current_campus_id' => Campus::factory()->create()->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.login'))
        ->assertForbidden();
});

it('returns 403 on sync without manage_crm_value_mapping', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    session(['current_campus_id' => Campus::factory()->create()->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.sync'))
        ->assertForbidden();
});

it('logs in and persists the token', function () {
    $staff = grantCrmSyncPermission(User::factory()->create(['type' => UserType::STAFF]), $campus = Campus::factory()->create());
    session(['current_campus_id' => $campus->id]);

    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'fresh-tok']], 200),
    ]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.login'));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(app(CrmIntegrationSettings::class)->resolveToken()['token'])->toBe('fresh-tok');
});

it('flashes a plain error message when CRM login fails, with no credential in it', function () {
    $staff = grantCrmSyncPermission(User::factory()->create(['type' => UserType::STAFF]), $campus = Campus::factory()->create());
    session(['current_campus_id' => $campus->id]);

    Http::fake([
        'https://crm.test/api/login' => Http::response(['message' => 'bad credentials'], 401),
    ]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.login'));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->not->toContain('bad credentials');
    expect(app(CrmIntegrationSettings::class)->resolveToken())->toBeNull();
});

it('refuses to sync when no token has been stored yet, without calling the CRM', function () {
    $staff = grantCrmSyncPermission(User::factory()->create(['type' => UserType::STAFF]), $campus = Campus::factory()->create());
    session(['current_campus_id' => $campus->id]);

    Http::fake();

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.sync'));

    $response->assertSessionHas('error');
    $response->assertSessionMissing('crm_sync_summary');
    Http::assertNothingSent();
});

it('runs a sync using a previously stored token and flashes the summary', function () {
    $staff = grantCrmSyncPermission(User::factory()->create(['type' => UserType::STAFF]), $campus = Campus::factory()->create());
    session(['current_campus_id' => $campus->id]);
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');

    Http::fake([
        'https://crm.test/api/ne' => Http::response(['data' => [['student_code' => 'SYNC0000001', 'name' => 'Sync UI Test']]], 200),
    ]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.sync'));

    $response->assertRedirect();
    $response->assertSessionHas('crm_sync_summary', fn (array $summary): bool => $summary['created'] === 1 && $summary['failed'] === 0);
    expect(StudentApplication::where('student_code', 'SYNC0000001')->exists())->toBeTrue();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/login'));
});

it('reports a warning-flavored summary when some records fail', function () {
    $staff = grantCrmSyncPermission(User::factory()->create(['type' => UserType::STAFF]), $campus = Campus::factory()->create());
    session(['current_campus_id' => $campus->id]);
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');

    Http::fake([
        'https://crm.test/api/ne' => Http::response(['data' => [
            ['student_code' => 'SYNC0000002', 'name' => 'Good'],
            ['student_code' => 'SYNC0000003', 'name' => 'Bad', 'date_of_birth' => 'garbage'],
        ]], 200),
    ]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_SYNC_ACTION_CSRF)
        ->post(route('student-applications.crm-mappings.sync'));

    $response->assertSessionHas('warning');
    $response->assertSessionHas('crm_sync_summary', fn (array $summary): bool => $summary['created'] === 1 && $summary['failed'] === 1);
});
