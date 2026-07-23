<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function grantSystemConfigurationPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode, 'module' => 'system_configuration'],
    );
    $role = Role::factory()->create(['code' => 'platform_'.Str::lower(Str::random(10))]);

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

beforeEach(function (): void {
    Cache::forget('system_config');
    Storage::fake('local');
    Storage::put('system_config.json', json_encode([
        'app_name' => 'Swinx',
        'logo_full' => '/storage/branding/logo-full.png',
        'logo_text' => '/storage/branding/logo-text.svg',
        'copyright_text' => '© Swinx',
        'country' => 'Việt Nam',
        'survey_enabled' => true,
        'private_future_setting' => 'must-not-be-public',
    ], JSON_THROW_ON_ERROR));

    $this->campus = Campus::factory()->create();
    $this->staff = User::factory()->create();
    $this->unprivilegedStaff = User::factory()->create();

    session([
        '_token' => 'system-configuration-test-token',
        'current_campus_id' => $this->campus->id,
    ]);
});

it('enumerates the public branding configuration without exposing arbitrary keys', function (): void {
    $this->getJson(route('api.system-config.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.app_name', 'Swinx')
        ->assertJsonPath('data.survey_enabled', true)
        ->assertJsonMissing(['private_future_setting' => 'must-not-be-public']);

    $this->getJson(route('api.system-config.show', ['key' => 'app_name']))
        ->assertOk()
        ->assertJsonPath('data.app_name', 'Swinx');

    $this->getJson(route('api.system-config.show', ['key' => 'private_future_setting']))
        ->assertNotFound();
});

it('rejects anonymous and unauthorized system configuration mutations', function (): void {
    $payload = ['app_name' => 'Changed without permission'];

    $this->putJson(route('api.system-config.update'), $payload)
        ->assertUnauthorized();

    $this->actingAs($this->unprivilegedStaff)
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->putJson(route('api.system-config.update'), $payload)
        ->assertForbidden();

    $this->postJson(route('api.system-config.upload'), [
        'file' => UploadedFile::fake()->image('logo.png'),
        'config_key' => 'logo_full',
    ])->assertForbidden();
});

it('lets authorized staff update the global configuration and records audit evidence', function (): void {
    grantSystemConfigurationPermission($this->staff, $this->campus, 'manage_system_config');

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->putJson(route('api.system-config.update'), [
            'app_name' => 'Swinx Platform',
            'country' => 'Vietnam',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.app_name', 'Swinx Platform')
        ->assertJsonPath('data.country', 'Vietnam');

    $saved = json_decode(Storage::get('system_config.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($saved)
        ->toMatchArray(['app_name' => 'Swinx Platform', 'country' => 'Vietnam'])
        ->and($saved['private_future_setting'])->toBe('must-not-be-public');

    $audit = DB::table('activity_log')
        ->where('description', 'System configuration updated')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->log_name)->toBe('system_configuration_system')
        ->and($audit->causer_id)->toBe($this->staff->id)
        ->and(json_decode($audit->properties, true, 512, JSON_THROW_ON_ERROR)['scope'])->toBe('global');
});

it('allows authorized staff to upload a branding file through the protected legacy endpoint', function (): void {
    grantSystemConfigurationPermission($this->staff, $this->campus, 'manage_system_config');

    Storage::put('system_config.json', json_encode([
        'app_name' => 'Swinx',
        'logo_full' => '/storage/branding/custom-logo.png',
        'logo_text' => '/storage/branding/logo-text.svg',
        'copyright_text' => '© Swinx',
        'country' => 'Việt Nam',
    ], JSON_THROW_ON_ERROR));

    $this->actingAs($this->staff)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->post(route('api.system-config.upload'), [
            'file' => UploadedFile::fake()->image('logo.png'),
            'config_key' => 'logo_full',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.path', '/storage/branding/custom-logo.png');

    Storage::disk('public')->assertExists('branding/custom-logo.png');

    $audit = DB::table('activity_log')
        ->where('description', 'System configuration file uploaded')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($this->staff->id);
});

it('preserves the staff page behind a view permission and its legacy URL', function (): void {
    grantSystemConfigurationPermission($this->staff, $this->campus, 'view_system_config');

    $this->actingAs($this->unprivilegedStaff)
        ->get(route('system.config.index'))
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->get(route('system.config.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SystemConfig/Index')
            ->where('config.app_name', 'Swinx')
            ->where('permissions.can_manage', false));
});
