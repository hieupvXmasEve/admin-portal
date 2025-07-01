<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clean existing data
        $this->cleanExistingData();

        // Create campuses
        $this->createCampuses();

        // Create roles
        $this->createRoles();

        // Create permissions
        $this->createPermissions();

        // Assign all permissions to super admin role
        $this->assignPermissionsToSuperAdmin();

        // Create super admin user
        $this->createSuperAdminUser();

        // Assign super admin to all campuses
        $this->assignSuperAdminToCampuses();
    }

    private function cleanExistingData(): void
    {
        CampusUserRole::query()->delete();
        RolePermission::query()->delete();
        User::query()->delete();
        Permission::query()->delete();
        Role::query()->delete();
        Campus::query()->delete();
    }

    private function createCampuses(): void
    {
        $campuses = [
            ['id' => 1, 'name' => 'Swinburne Hà Nội', 'code' => 'HN', 'address' => ''],
            ['id' => 2, 'name' => 'Swinburne Hồ Chí Minh', 'code' => 'HCM', 'address' => ''],
            ['id' => 3, 'name' => 'Swinburne Đà Nẵng', 'code' => 'DN', 'address' => ''],
            ['id' => 4, 'name' => 'Swinburne Cần Thơ', 'code' => 'CT', 'address' => ''],
        ];

        foreach ($campuses as $campus) {
            Campus::create($campus);
        }
    }

    private function createRoles(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Super Admin', 'code' => 'super_admin'],
            ['id' => 2, 'name' => 'Giám Đốc Đào Tạo', 'code' => 'giam_doc_dao_tao'],
            ['id' => 3, 'name' => 'Trưởng Phòng', 'code' => 'truong_phong'],
            ['id' => 4, 'name' => 'Cán Bộ', 'code' => 'can_bo'],
            ['id' => 5, 'name' => 'Giảng Viên', 'code' => 'giang_vien'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }

    private function createPermissions(): void
    {
        $permissionConfig = config('permission.access');
        $permissionId = 1;

        foreach ($permissionConfig as $module => $actions) {
            // Create permissions for each action directly (skip parent permissions)
            foreach ($actions as $actionName => $code) {
                Permission::create([
                    'id' => $permissionId++,
                    'name' => $code, // Use the code as name for consistency
                    'code' => $code, // The actual permission code
                    'display_name' => ucfirst(str_replace('_', ' ', $actionName)),
                    'module' => $module,
                    'description' => "Permission to {$actionName} in {$module} module",
                ]);
            }
        }
    }

    private function assignPermissionsToSuperAdmin(): void
    {
        $superAdminRole = Role::where('code', 'super_admin')->first();
        $permissions = Permission::all(); // Get all permissions since we removed parent/child structure

        foreach ($permissions as $permission) {
            RolePermission::create([
                'role_id' => $superAdminRole->id,
                'permission_id' => $permission->id,
            ]);
        }
    }

    private function createSuperAdminUser(): void
    {
        User::create([
            'id' => 1,
            'name' => 'Super Admin',
            'email' => 'hieupv2412@gmail.com',
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        // Create additional test users if in local environment
        if (app()->environment('local')) {
            User::factory(50)->create();
        }
    }

    private function assignSuperAdminToCampuses(): void
    {
        $superAdminUser = User::find(1);
        $superAdminRole = Role::where('code', 'super_admin')->first();
        $campuses = Campus::all();

        foreach ($campuses as $campus) {
            CampusUserRole::create([
                'user_id' => $superAdminUser->id,
                'campus_id' => $campus->id,
                'role_id' => $superAdminRole->id,
            ]);
        }
    }
}
