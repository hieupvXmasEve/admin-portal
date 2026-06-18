<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates roles and permissions based on config/permission.php
     */
    public function run(): void
    {
        $this->command->info('🔐 Creating roles and permissions...');

        // Clean existing data
        $this->cleanExistingData();

        // Create permissions from config
        $this->createPermissions();

        // Create roles
        $this->createRoles();

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Roles and permissions created successfully!');
    }

    private function cleanExistingData(): void
    {
        RolePermission::query()->delete();
        Permission::query()->delete();
        Role::query()->delete();
    }

    private function createPermissions(): void
    {
        $permissionConfig = config('permission.access');
        $permissionId = 1;

        foreach ($permissionConfig as $module => $actions) {
            foreach ($actions as $actionName => $code) {
                Permission::create([
                    'id' => $permissionId++,
                    'name' => $code,
                    'code' => $code,
                    'display_name' => ucfirst(str_replace('_', ' ', $actionName)),
                    'module' => $module,
                    'description' => "Permission to {$actionName} in {$module} module",
                ]);
            }
        }

        $this->command->info('📋 Created '.($permissionId - 1).' permissions');
    }

    private function createRoles(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Super Admin', 'code' => 'super_admin'],
            ['id' => 2, 'name' => 'Giám Đốc Đào Tạo', 'code' => 'giam_doc_dao_tao'],
            ['id' => 3, 'name' => 'Trưởng Phòng', 'code' => 'truong_phong'],
            ['id' => 4, 'name' => 'Cán Bộ', 'code' => 'can_bo'],
            ['id' => 5, 'name' => 'Phụ huynh', 'code' => 'parent'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }

        $this->command->info('👥 Created '.count($roles).' roles');
    }

    private function assignPermissionsToRoles(): void
    {
        // Super Admin gets all permissions
        $this->assignAllPermissionsToSuperAdmin();

        // Other roles get specific permissions
        $this->assignPermissionsToOtherRoles();
    }

    private function assignAllPermissionsToSuperAdmin(): void
    {
        $superAdminRole = Role::where('code', 'super_admin')->first();
        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            RolePermission::create([
                'role_id' => $superAdminRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        $this->command->info("🔑 Assigned {$permissions->count()} permissions to Super Admin");
    }

    private function assignPermissionsToOtherRoles(): void
    {
        // Define role-specific permissions
        $rolePermissions = [
            'giam_doc_dao_tao' => [
                'view_user',
                'create_user',
                'edit_user',
                'view_campus',
                'edit_campus',
                'view_program',
                'create_program',
                'edit_program',
                'view_unit',
                'create_unit',
                'edit_unit',
                'view_student',
                'create_student',
                'edit_student',
                'view_semester',
                'create_semester',
                'edit_semester',
                'view_course',
                'create_course',
                'edit_course',
            ],
            'truong_phong' => [
                'view_user',
                'create_user',
                'edit_user',
                'view_program',
                'edit_program',
                'view_unit',
                'edit_unit',
                'view_student',
                'create_student',
                'edit_student',
                'view_course',
                'create_course',
                'edit_course',
                // EGC Operations
                'view_egc_finance_operations',
                'generate_egc_finance_charges',
                'view_egc_block_results',
                'sync_egc_block_results',
                'view_egc_retake_adjustments',
                'apply_egc_retake_adjustment',
                // Staff workspace foundation (S-010 milestone 1)
                'view_finance_student_overview',
                'view_finance_batch_studio',
                'view_finance_cockpit',
                'view_finance_reporting',
            ],
            'can_bo' => [
                'view_user',
                'view_student',
                'create_student',
                'edit_student',
                'view_course',
                'view_program',
                'view_unit',
                // EGC Operations
                'view_egc_finance_operations',
                'generate_egc_finance_charges',
                'view_egc_block_results',
                'sync_egc_block_results',
                'view_egc_retake_adjustments',
                'apply_egc_retake_adjustment',
                // Staff workspace foundation (S-010 milestone 1)
                'view_finance_student_overview',
                'view_finance_batch_studio',
                'view_finance_cockpit',
                'view_finance_reporting',
            ],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $role = Role::where('code', $roleCode)->first();
            if (! $role) {
                continue;
            }

            foreach ($permissionCodes as $permissionCode) {
                $permission = Permission::where('code', $permissionCode)->first();
                if ($permission) {
                    RolePermission::create([
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ]);
                }
            }

            $this->command->info('🔐 Assigned '.count($permissionCodes)." permissions to {$role->name}");
        }
    }
}
