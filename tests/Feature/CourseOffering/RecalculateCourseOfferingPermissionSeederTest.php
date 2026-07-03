<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Database\Seeders\UpdatePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds recalculate_course_offering and does not grant it to roles holding edit_course_offering by default', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $recalculate = Permission::where('code', 'recalculate_course_offering')->first();
    $edit = Permission::where('code', 'edit_course_offering')->first();

    expect($recalculate)->not->toBeNull()
        ->and($edit)->not->toBeNull();

    $editorRoleIds = RolePermission::where('permission_id', $edit->id)->pluck('role_id');
    expect($editorRoleIds)->not->toBeEmpty();

    $superAdminRoleId = Role::where('code', 'super_admin')->value('id');

    foreach ($editorRoleIds as $roleId) {
        if ($roleId === $superAdminRoleId) {
            // Super Admin holds every permission unconditionally; that is not
            // the auto-grant this test is guarding against.
            continue;
        }

        expect(
            RolePermission::where('role_id', $roleId)
                ->where('permission_id', $recalculate->id)
                ->exists()
        )->toBeFalse("Role {$roleId} holds edit_course_offering and should not have received recalculate_course_offering by default");
    }
});

it('creates recalculate_course_offering when syncing permissions on a live database without granting it to editor roles', function () {
    Role::create(['name' => 'Super Admin', 'code' => 'super_admin']);
    $editorRole = Role::create(['name' => 'Academic Staff', 'code' => 'academic_staff']);
    $edit = Permission::create([
        'name' => 'edit_course_offering',
        'code' => 'edit_course_offering',
        'display_name' => 'Edit course offering',
        'module' => 'course_offerings',
        'description' => 'Permission to edit_course_offering in course_offerings module',
    ]);
    RolePermission::create(['role_id' => $editorRole->id, 'permission_id' => $edit->id]);

    $this->seed(UpdatePermissionsSeeder::class);

    $recalculate = Permission::where('code', 'recalculate_course_offering')->first();
    expect($recalculate)->not->toBeNull()
        ->and(
            RolePermission::where('role_id', $editorRole->id)
                ->where('permission_id', $recalculate->id)
                ->exists()
        )->toBeFalse();
});
