<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Modules\Platform\Actions\UpdateSystemConfigurationAction;
use App\Modules\Platform\Models\SystemSetting;
use App\Modules\Platform\Queries\GetSystemBrandingQuery;
use App\Modules\Platform\Support\SystemConfigurationStore;
use App\Modules\Upload\Models\UploadRecord;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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

function grantSystemConfigurationSuperAdmin(User $user, Campus $campus): void
{
    $role = Role::query()->where('code', 'super_admin')->firstOrFail();

    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}

beforeEach(function (): void {
    Cache::flush();
    Storage::fake('local');
    Storage::fake('public');

    $this->campus = Campus::factory()->create();
    $this->staff = User::factory()->create();
    $this->unprivilegedStaff = User::factory()->create();

    session([
        '_token' => 'system-configuration-test-token',
        'current_campus_id' => $this->campus->id,
    ]);
});

it('materializes deterministic typed defaults without reading the legacy JSON file', function (): void {
    Storage::put('system_config.json', json_encode([
        'app_name' => 'Legacy value that must not be imported',
        'survey_enabled' => true,
    ], JSON_THROW_ON_ERROR));

    $configuration = app(SystemConfigurationStore::class)->all();

    expect($configuration)
        ->toMatchArray([
            'app_name' => 'Swinx',
            'copyright_text' => '© 2026 Asia Vietnam University. All rights reserved.',
            'country' => 'Việt Nam',
            'survey_enabled' => false,
            'default_course_survey' => null,
            'active_query_forms' => [],
            'system_booking_start_time' => '07:00',
            'system_booking_end_time' => '20:00',
            'allow_student_booking' => true,
            'student_booking_limit_per_day' => 2,
            'logo_full_upload_id' => null,
            'logo_text_upload_id' => null,
            'favicon_upload_id' => null,
            'apple_touch_icon_upload_id' => null,
        ])
        ->and(SystemSetting::query()->count())->toBe(14);
});

it('enumerates only code-approved public configuration keys', function (): void {
    $this->getJson(route('api.system-config.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.app_name', 'Swinx')
        ->assertJsonPath('data.survey_enabled', false)
        ->assertJsonMissing(['data.default_course_survey'])
        ->assertJsonMissing(['data.logo_full_upload_id']);

    $this->getJson(route('api.system-config.show', ['key' => 'app_name']))
        ->assertOk()
        ->assertJsonPath('data.app_name', 'Swinx');

    $this->getJson(route('api.system-config.show', ['key' => 'default_course_survey']))
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

it('rejects a campus-only manager from mutating global configuration', function (): void {
    grantSystemConfigurationPermission($this->staff, $this->campus, 'manage_system_config');

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->putJson(route('api.system-config.update'), ['app_name' => 'Campus manager change'])
        ->assertForbidden();
});

it('lets a system super administrator update typed configuration and records audit evidence', function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    grantSystemConfigurationSuperAdmin($this->staff, $this->campus);

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->putJson(route('api.system-config.update'), [
            'app_name' => 'Swinx Platform',
            'country' => 'Vietnam',
            'survey_enabled' => true,
            'active_query_forms' => [12, 24],
            'default_course_survey' => null,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.app_name', 'Swinx Platform')
        ->assertJsonPath('data.country', 'Vietnam')
        ->assertJsonPath('data.survey_enabled', true)
        ->assertJsonMissing(['data.active_query_forms']);

    expect(SystemSetting::query()->where('key', 'app_name')->firstOrFail()->value)->toBe('Swinx Platform')
        ->and(SystemSetting::query()->where('key', 'survey_enabled')->firstOrFail()->value)->toBeTrue()
        ->and(SystemSetting::query()->where('key', 'active_query_forms')->firstOrFail()->value)->toBe([12, 24]);

    $audit = DB::table('activity_log')
        ->where('description', 'System configuration updated')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->log_name)->toBe('system_configuration_system')
        ->and($audit->causer_id)->toBe($this->staff->id)
        ->and(json_decode($audit->properties, true, 512, JSON_THROW_ON_ERROR)['scope'])->toBe('global');
});

it('rejects unsupported setting keys instead of silently accepting them', function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    grantSystemConfigurationSuperAdmin($this->staff, $this->campus);

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->putJson(route('api.system-config.update'), [
            'private_future_setting' => 'must-not-persist',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'configuration');

    expect(SystemSetting::query()->where('key', 'private_future_setting')->exists())->toBeFalse();
});

it('invalidates the shared snapshot only after its transaction commits', function (): void {
    $store = app(SystemConfigurationStore::class);
    $action = app(UpdateSystemConfigurationAction::class);

    expect($store->all()['country'])->toBe('Việt Nam');

    try {
        DB::transaction(function () use ($action): void {
            $action->update(['country' => 'Rolled back']);

            throw new RuntimeException('Force rollback.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Force rollback.');
    }

    expect($store->all()['country'])->toBe('Việt Nam');

    $action->update(['country' => 'Vietnam']);

    expect($store->all()['country'])->toBe('Vietnam');
});

it('fails readiness when a required setting row is missing', function (): void {
    SystemSetting::query()->where('key', 'allow_student_booking')->delete();
    Cache::flush();

    app(SystemConfigurationStore::class)->all();
})->throws(RuntimeException::class, 'required keys are missing: allow_student_booking');

it('keeps the protected upload API compatible while returning an immutable branding URL', function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    grantSystemConfigurationSuperAdmin($this->staff, $this->campus);

    $response = $this->actingAs($this->staff)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-CSRF-TOKEN', 'system-configuration-test-token')
        ->post(route('api.system-config.upload'), [
            'file' => UploadedFile::fake()->image('logo.png'),
            'config_key' => 'logo_full',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $path = parse_url($response->json('data.path'), PHP_URL_PATH);

    $upload = UploadRecord::query()->findOrFail(
        SystemSetting::query()->where('key', 'logo_full_upload_id')->firstOrFail()->value,
    );
    $upload->forceFill(['url' => '/storage/branding/legacy-logo.png'])->save();

    expect($path)
        ->toStartWith('/storage/images/branding/')
        ->not->toBe('/storage/images/branding/logo-full.png')
        ->and(app(GetSystemBrandingQuery::class)->handle()['logo_full_url'])
        ->toContain('/storage/images/branding/');
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
