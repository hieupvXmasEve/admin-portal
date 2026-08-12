<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Real Authorize middleware, no Gate::before bypass — proves can:view_student_action
 * actually gates this route (the sibling ScholarshipRestorationWatchlistWebRoutesTest
 * strips Authorize entirely for its characterization coverage, so it can't prove this).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('rejects the index for a user without view_student_action', function (): void {
    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id]);
    actingAs(User::factory()->create());

    get(route('reports.scholarship-restorations.index'))->assertForbidden();
});

it('allows the index and reports can.propose/can.approve for a granted user', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'watchlist_route_view_test'], ['name' => 'Watchlist Route View Test']);

    foreach (['view_student_action', 'restore_scholarship'] as $code) {
        $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'display_name' => $code, 'module' => 'scholarship_adjustments', 'description' => 'test']);
        DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    }
    DB::table('campus_user_roles')->insert(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    session(['current_campus_id' => $campus->id]);
    actingAs($user);

    get(route('reports.scholarship-restorations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.propose', true)
            ->where('can.approve', false)
        );
});
