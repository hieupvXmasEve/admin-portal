<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Database\Seeders\UpdatePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds complete_course_offering and grants it to every role holding edit_course_offering', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $complete = Permission::where('code', 'complete_course_offering')->first();
    $edit = Permission::where('code', 'edit_course_offering')->first();

    expect($complete)->not->toBeNull()
        ->and($edit)->not->toBeNull();

    $editorRoleIds = RolePermission::where('permission_id', $edit->id)->pluck('role_id');
    expect($editorRoleIds)->not->toBeEmpty();

    foreach ($editorRoleIds as $roleId) {
        expect(
            RolePermission::where('role_id', $roleId)
                ->where('permission_id', $complete->id)
                ->exists()
        )->toBeTrue("Role {$roleId} holds edit_course_offering but not complete_course_offering");
    }
});

it('grants complete_course_offering to existing editor roles when syncing permissions on a live database', function () {
    // Simulate a live database: an editor role exists before the new permission lands.
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

    $complete = Permission::where('code', 'complete_course_offering')->first();
    expect($complete)->not->toBeNull()
        ->and(
            RolePermission::where('role_id', $editorRole->id)
                ->where('permission_id', $complete->id)
                ->exists()
        )->toBeTrue();
});
