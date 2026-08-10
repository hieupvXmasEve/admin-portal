<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Modules\Admissions\Integrations\Crm\CrmClient;
use App\Modules\Admissions\Models\CrmIntegrationSetting;
use App\Modules\Admissions\Support\Crm\CrmIntegrationSettings;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const CRM_INTEGRATION_CSRF = 'crm-integration-settings-csrf';

beforeEach(function () {
    Cache::flush();
    session(['_token' => CRM_INTEGRATION_CSRF]);
    config([
        'services.crm.login_url' => 'https://env-fallback.test/api/login',
        'services.crm.data_url' => 'https://env-fallback.test/api/ne',
        'services.crm.username' => 'env-user',
        'services.crm.password' => 'env-password',
        'services.crm.timeout' => 42,
    ]);
});

function grantCrmIntegrationPermission(User $user, Campus $campus): void
{
    $permission = Permission::firstOrCreate(['code' => 'manage_crm_value_mapping'], ['name' => 'manage_crm_value_mapping']);
    $role = Role::factory()->create(['code' => 'crm_int_'.Str::lower(Str::random(10))]);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id]);
}

it('falls back to .env config when no DB row exists', function () {
    $resolved = app(CrmIntegrationSettings::class)->resolve();

    expect($resolved)->toBe([
        'login_url' => 'https://env-fallback.test/api/login',
        'data_url' => 'https://env-fallback.test/api/ne',
        'username' => 'env-user',
        'password' => 'env-password',
        'timeout' => 42,
    ]);
});

it('the DB row wins over .env once configured', function () {
    app(CrmIntegrationSettings::class)->save([
        'login_url' => 'https://db.test/login',
        'data_url' => 'https://db.test/ne',
        'username' => 'db-user',
        'password' => 'db-password',
        'timeout' => 99,
    ]);

    $resolved = app(CrmIntegrationSettings::class)->resolve();

    expect($resolved)->toBe([
        'login_url' => 'https://db.test/login',
        'data_url' => 'https://db.test/ne',
        'username' => 'db-user',
        'password' => 'db-password',
        'timeout' => 99,
    ]);
});

it('encrypts the password at rest', function () {
    app(CrmIntegrationSettings::class)->save([
        'login_url' => 'https://db.test/login',
        'data_url' => 'https://db.test/ne',
        'username' => 'db-user',
        'password' => 'plaintext-secret',
        'timeout' => 99,
    ]);

    $raw = \Illuminate\Support\Facades\DB::table('crm_integration_settings')->where('id', CrmIntegrationSetting::ROW_ID)->value('password');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain('plaintext-secret');
});

it('never exposes the password in forDisplay(), only a boolean flag', function () {
    app(CrmIntegrationSettings::class)->save([
        'login_url' => 'https://db.test/login',
        'data_url' => 'https://db.test/ne',
        'username' => 'db-user',
        'password' => 'plaintext-secret',
        'timeout' => 99,
    ]);

    $display = app(CrmIntegrationSettings::class)->forDisplay();

    expect($display)->not->toHaveKey('password')
        ->and($display['has_password'])->toBeTrue();
});

it('a blank password on save keeps the existing one', function () {
    app(CrmIntegrationSettings::class)->save(['login_url' => 'https://db.test/login', 'data_url' => 'https://db.test/ne', 'username' => 'u', 'password' => 'first-secret', 'timeout' => 60]);
    app(CrmIntegrationSettings::class)->save(['login_url' => 'https://db.test/login', 'data_url' => 'https://db.test/ne', 'username' => 'u', 'password' => null, 'timeout' => 60]);

    expect(app(CrmIntegrationSettings::class)->resolve()['password'])->toBe('first-secret');
});

it('encrypts the token at rest and exposes only a logged_in flag', function () {
    app(CrmIntegrationSettings::class)->saveToken('plaintext-token', 'Bearer');

    $raw = \Illuminate\Support\Facades\DB::table('crm_integration_settings')->where('id', CrmIntegrationSetting::ROW_ID)->value('token');
    expect($raw)->not->toBeNull()->and($raw)->not->toContain('plaintext-token');

    $display = app(CrmIntegrationSettings::class)->forDisplay();
    expect($display)->not->toHaveKey('token')
        ->and($display['logged_in'])->toBeTrue()
        ->and($display['token_obtained_at'])->not->toBeNull();
});

it('resolveToken returns null when nothing has been stored', function () {
    expect(app(CrmIntegrationSettings::class)->resolveToken())->toBeNull();
});

it('CrmClient uses the DB-configured login_url over .env', function () {
    app(CrmIntegrationSettings::class)->save(['login_url' => 'https://db-crm.test/login', 'data_url' => 'https://db-crm.test/ne', 'username' => 'db-user', 'password' => 'db-pass', 'timeout' => 30]);

    Http::fake([
        'https://db-crm.test/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok']], 200),
    ]);

    app(CrmClient::class)->login();

    Http::assertSent(fn ($request) => $request->url() === 'https://db-crm.test/login');
});

it('returns 403 saving integration settings without manage_crm_value_mapping', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    session(['current_campus_id' => Campus::factory()->create()->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_INTEGRATION_CSRF)
        ->post(route('student-applications.crm-mappings.integration.store'), ['login_url' => 'https://x.test/login', 'data_url' => 'https://x.test/ne', 'username' => 'u', 'timeout' => 60])
        ->assertForbidden();
});

it('saves integration settings through the web endpoint', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    $campus = Campus::factory()->create();
    grantCrmIntegrationPermission($staff, $campus);
    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_INTEGRATION_CSRF)
        ->post(route('student-applications.crm-mappings.integration.store'), [
            'login_url' => 'https://new-crm.test/login',
            'data_url' => 'https://new-crm.test/ne',
            'username' => 'new-user',
            'password' => 'new-password',
            'timeout' => 90,
        ]);

    $response->assertRedirect();
    expect(app(CrmIntegrationSettings::class)->resolve())->toBe([
        'login_url' => 'https://new-crm.test/login',
        'data_url' => 'https://new-crm.test/ne',
        'username' => 'new-user',
        'password' => 'new-password',
        'timeout' => 90,
    ]);
});

it('rejects an invalid login_url', function () {
    $staff = User::factory()->create(['type' => UserType::STAFF]);
    $campus = Campus::factory()->create();
    grantCrmIntegrationPermission($staff, $campus);
    session(['current_campus_id' => $campus->id]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', CRM_INTEGRATION_CSRF)
        ->post(route('student-applications.crm-mappings.integration.store'), ['login_url' => 'not-a-url', 'data_url' => 'https://x.test/ne', 'username' => 'u', 'timeout' => 60])
        ->assertSessionHasErrors('login_url');
});
