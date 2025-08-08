<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Console\Command;

class CreateImportPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:create-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create import and export permissions for users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating import and export permissions...');

        // Create import_user permission
        $importPermission = Permission::firstOrCreate([
            'code' => 'import_user',
        ], [
            'name' => 'Import Users',
            'description' => 'Can import users from Excel files',
            'parent_id' => null,
        ]);

        // Create export_user permission
        $exportPermission = Permission::firstOrCreate([
            'code' => 'export_user',
        ], [
            'name' => 'Export Users',
            'description' => 'Can export users to Excel files',
            'parent_id' => null,
        ]);

        $this->info('Permissions created:');
        $this->line("- {$importPermission->name} ({$importPermission->code})");
        $this->line("- {$exportPermission->name} ({$exportPermission->code})");

        // Assign to Super Admin role if it exists
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            // Check if permissions are already assigned
            $importExists = RolePermission::where('role_id', $superAdminRole->id)
                ->where('permission_id', $importPermission->id)
                ->exists();

            $exportExists = RolePermission::where('role_id', $superAdminRole->id)
                ->where('permission_id', $exportPermission->id)
                ->exists();

            if (! $importExists) {
                RolePermission::create([
                    'role_id' => $superAdminRole->id,
                    'permission_id' => $importPermission->id,
                ]);
                $this->info('Assigned import_user permission to Super Admin role');
            }

            if (! $exportExists) {
                RolePermission::create([
                    'role_id' => $superAdminRole->id,
                    'permission_id' => $exportPermission->id,
                ]);
                $this->info('Assigned export_user permission to Super Admin role');
            }

            if ($importExists && $exportExists) {
                $this->info('Permissions already assigned to Super Admin role');
            }
        } else {
            $this->warn('Super Admin role not found. Please assign permissions manually.');
        }

        $this->info('Import permissions setup completed!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Make sure users have the appropriate roles assigned');
        $this->line('2. Clear any cached permissions if needed');
        $this->line('3. Test the import functionality');

        return 0;
    }
}
