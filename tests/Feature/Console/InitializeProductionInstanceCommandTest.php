<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

uses(RefreshDatabase::class);

function initializeProductionInstance(array $overrides = []): PendingCommand
{
    return test()->artisan('instance:initialize', array_merge([
        '--institution' => 'School A',
        '--campus-code' => 'SCA',
        '--campus-address' => '1 Example Street',
        '--admin-name' => 'School Admin',
        '--admin-email' => 'admin@school-a.test',
        '--admin-password' => 'a-secure-admin-password',
        '--force' => true,
    ], $overrides));
}

it('initializes an empty instance with one super-admin', function () {
    initializeProductionInstance()
        ->expectsOutput('Instance initialized with one campus and one super-admin.')
        ->assertSuccessful();

    $campus = Campus::query()->sole();
    $administrator = User::query()->sole();
    $superAdminRole = Role::query()->where('code', 'super_admin')->sole();

    expect($campus->only(['name', 'code', 'address']))->toBe([
        'name' => 'School A',
        'code' => 'SCA',
        'address' => '1 Example Street',
    ]);
    expect($administrator->email)->toBe('admin@school-a.test');
    expect(Hash::check('a-secure-admin-password', $administrator->password))->toBeTrue();

    $this->assertDatabaseHas('campus_user_roles', [
        'user_id' => $administrator->id,
        'campus_id' => $campus->id,
        'role_id' => $superAdminRole->id,
    ]);
    expect(CampusUserRole::query()->count())->toBe(1);
});

it('requires explicit confirmation before initializing an empty instance', function () {
    initializeProductionInstance(['--force' => false])
        ->expectsOutput('Pass --force to confirm initialization of this instance.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses to initialize an instance that already has data', function () {
    Campus::factory()->create();

    initializeProductionInstance()
        ->expectsOutput('Initialization stopped: roles, permissions, campuses, or users already exist.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
