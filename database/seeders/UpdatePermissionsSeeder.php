<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class UpdatePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder updates the permissions table from the config file
     * and ensures the super_admin has all permissions.
     *
     * This is non-destructive and can be run multiple times.
     */
    public function run(): void
    {
        $this->command->info('🔄 Updating permissions from config...');

        // Create or update permissions from the configuration file.
        $this->createOrUpdatePermissions();

        // Assign all available permissions to the Super Admin role.
        $this->assignAllPermissionsToSuperAdmin();

        $this->command->info('✅ Permissions updated successfully!');
    }

    /**
     * Creates new permissions from the config file if they don't already exist.
     */
    private function createOrUpdatePermissions(): void
    {
        $permissionConfig = config('permission.access');
        $createdCount = 0;

        foreach ($permissionConfig as $module => $actions) {
            foreach ($actions as $actionName => $code) {
                $permission = Permission::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'display_name' => ucfirst(str_replace('_', ' ', $actionName)),
                        'module' => $module,
                        'description' => "Permission to {$actionName} in {$module} module",
                    ]
                );

                if ($permission->wasRecentlyCreated) {
                    $createdCount++;
                    $this->command->line("  → Created new permission: <info>{$code}</info>");
                }
            }
        }

        if ($createdCount > 0) {
            $this->command->info("🆕 Found and created {$createdCount} new permissions.");
        } else {
            $this->command->info('✨ All permissions are already up-to-date.');
        }
    }

    /**
     * Assigns all existing permissions to the 'super_admin' role.
     */
    private function assignAllPermissionsToSuperAdmin(): void
    {
        $superAdminRole = Role::where('code', 'super_admin')->first();

        if (!$superAdminRole) {
            $this->command->error('❌ Super Admin role not found. Skipping permission assignment.');
            Log::error('UpdatePermissionsSeeder: Super Admin role not found.');
            return;
        }

        $allPermissionIds = Permission::pluck('id')->all();
        $superAdminRole->permissions()->sync($allPermissionIds);

        $this->command->info("🔑 Synced {$superAdminRole->permissions()->count()} permissions to the Super Admin role.");
    }
}
