<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Log;

class RoleService
{
    /**
     * Create a new role.
     *
     * @param array $data Validated data.
     * @return Role
     */
    public function createRole(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        Log::info('Creating a new role', $data);
        $role = Role::create($data);

        if (!empty($permissions)) {
            $role->permissions()->sync($permissions);
        }

        return $role;
    }

    /**
     * Update an existing role.
     *
     * @param Role $role The role to update.
     * @param array $data Validated data.
     * @return Role
     */
    public function updateRole(Role $role, array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        Log::info("Updating role {$role->id}", $data);
        $role->update($data);

        $role->permissions()->sync($permissions);

        return $role;
    }

    /**
     * Delete a role.
     *
     * @param Role $role The role to delete.
     * @return void
     */
    public function deleteRole(Role $role): void
    {
        Log::warning("Deleting role {$role->id}");
        $role->permissions()->detach();
        $role->delete();
    }
}
