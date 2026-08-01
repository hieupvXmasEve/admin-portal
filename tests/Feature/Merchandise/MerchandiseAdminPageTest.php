<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Merchandise;
use App\Models\Role;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);

    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
});

it('renders the admin merchandise index as an Inertia page for a super_admin', function () {
    Merchandise::factory()->count(2)->create();

    $admin = User::factory()->create();
    $role = Role::where('code', 'super_admin')->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $admin->id,
        'campus_id' => $this->campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $admin->id);

    $this->actingAs($admin)
        ->get(route('merchandise.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Merchandise/Index')
            ->has('merchandise.data', 2)
            ->has('merchandise.current_page'));
});

it('rejects a user without view_merchandise on the index route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('merchandise.index'))
        ->assertForbidden();
});
