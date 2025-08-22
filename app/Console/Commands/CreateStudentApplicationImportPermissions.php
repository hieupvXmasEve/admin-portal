<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Console\Command;

class CreateStudentApplicationImportPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:create-student-application-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create import and export permissions for student applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating student application import and export permissions...');

        // Create import_student_application permission
        $importPermission = Permission::firstOrCreate([
            'code' => 'import_student_application',
        ], [
            'name' => 'Import Student Applications',
            'description' => 'Can import student applications from Excel files',
            'parent_id' => null,
        ]);

        // Create export_student_application permission
        $exportPermission = Permission::firstOrCreate([
            'code' => 'export_student_application',
        ], [
            'name' => 'Export Student Applications',
            'description' => 'Can export student applications to Excel files',
            'parent_id' => null,
        ]);

        $this->info('Permissions created:');
        $this->line("- {$importPermission->name} ({$importPermission->code})");
        $this->line("- {$exportPermission->name} ({$exportPermission->code})");

        // Assign to Super Admin role (role_id = 1)
        $superAdminRoleId = 1;
        
        RolePermission::firstOrCreate([
            'role_id' => $superAdminRoleId,
            'permission_id' => $importPermission->id,
        ]);
        
        RolePermission::firstOrCreate([
            'role_id' => $superAdminRoleId,
            'permission_id' => $exportPermission->id,
        ]);

        $this->info('Permissions assigned to Super Admin role.');
        $this->info('Student application import and export permissions created successfully!');
    }
}
