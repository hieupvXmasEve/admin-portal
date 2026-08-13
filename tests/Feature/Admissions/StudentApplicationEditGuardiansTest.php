<?php

declare(strict_types=1);

use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function editGuardiansGrantPermission(User $user, Campus $campus, string $permissionCode): void
{
    $permission = Permission::firstOrCreate(['code' => $permissionCode], ['name' => $permissionCode]);
    $role = Role::factory()->create(['code' => 'edit_guardians_'.Str::lower(Str::random(10))]);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id]);
}

it('passes the application guardians and relationship options to the Edit page', function () {
    $campus = Campus::factory()->create();
    $staff = User::factory()->create();
    editGuardiansGrantPermission($staff, $campus, 'edit_student_application');

    $application = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);
    $guardian = ApplicationGuardian::factory()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Phạm Đăng Khánh',
        'relationship' => 'father',
        'is_primary' => true,
    ]);

    session(['current_campus_id' => $campus->id]);
    $response = $this->actingAs($staff)->get(route('student-applications.edit', $application));

    $response->assertInertia(fn ($page) => $page
        ->component('StudentApplications/Edit')
        ->has('guardianRelationships')
        ->has('application.guardians', 1)
        ->where('application.guardians.0.id', $guardian->id)
        ->where('application.guardians.0.full_name', 'Phạm Đăng Khánh'));
});
