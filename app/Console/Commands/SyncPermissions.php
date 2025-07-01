<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Synchronize permissions from config to database';

    public function handle()
    {
        $configPermissions = config('permission.access', []);
        $existingPermissions = Permission::pluck('name')->toArray();
        $count = 0;

        foreach ($configPermissions as $module => $permissions) {
            foreach ($permissions as $key => $permission) {
                if (!in_array($permission, $existingPermissions)) {
                    Permission::create([
                        'name' => $permission,
                        'display_name' => ucwords(str_replace('_', ' ', $permission)),
                        'module' => $module,
                        'code' => $permission, // Using permission name as code for now
                    ]);

                    $this->info("Added permission: {$permission}");
                    $count++;
                }
            }
        }

        $this->info("Synchronized {$count} new permissions.");
    }
}
