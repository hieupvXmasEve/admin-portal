<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\Concerns\GrantsCompleteCourseOfferingToEditorRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class UpdatePermissionsSeeder extends Seeder
{
    use GrantsCompleteCourseOfferingToEditorRoles;

    /**
     * Run the database seeds.
     * This seeder syncs the permissions table with the config file
     * and ensures the super_admin has all permissions.
     *
     * This will create new, update existing, and DELETE orphaned permissions.
     */
    public function run(): void
    {
        $this->command->info('🔄 Syncing permissions from config...');

        // Create or update permissions from the configuration file.
        $this->createOrUpdatePermissions();

        // Delete permissions that exist in DB but not in config.
        $this->deleteOrphanedPermissions();

        // Assign all available permissions to the Super Admin role.
        $this->assignAllPermissionsToSuperAdmin();

        // Companion grants that follow another permission by default.
        $this->grantCompleteCourseOfferingToEditorRoles();

        $this->command->info('✅ Permissions synced successfully!');
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
     * Deletes permissions that exist in the database but not in the config file.
     */
    private function deleteOrphanedPermissions(): void
    {
        $permissionConfig = config('permission.access');
        $configCodes = [];

        foreach ($permissionConfig as $module => $actions) {
            foreach ($actions as $actionName => $code) {
                $configCodes[] = $code;
            }
        }

        $orphanedPermissions = Permission::whereNotIn('code', $configCodes)->get();

        if ($orphanedPermissions->isEmpty()) {
            $this->command->info('✨ No orphaned permissions to delete.');

            return;
        }

        $deletedCount = 0;
        foreach ($orphanedPermissions as $permission) {
            $this->command->line("  → Deleting orphaned permission: <comment>{$permission->code}</comment>");
            // Detach from all roles first (removes entries from role_permissions pivot table)
            $permission->roles()->detach();
            $permission->delete();
            $deletedCount++;
        }

        $this->command->info("🗑️  Deleted {$deletedCount} orphaned permissions.");
    }

    /**
     * Assigns all existing permissions to the 'super_admin' role.
     */
    private function assignAllPermissionsToSuperAdmin(): void
    {
        $superAdminRole = Role::where('code', 'super_admin')->first();

        if (! $superAdminRole) {
            $this->command->error('❌ Super Admin role not found. Skipping permission assignment.');
            Log::error('UpdatePermissionsSeeder: Super Admin role not found.');

            return;
        }

        $allPermissionIds = Permission::pluck('id')->all();
        $superAdminRole->permissions()->sync($allPermissionIds);

        $this->command->info("🔑 Synced {$superAdminRole->permissions()->count()} permissions to the Super Admin role.");
    }
}
