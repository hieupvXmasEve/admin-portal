<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Log;

class RoleService
{
    protected RoleAssignmentService $roleAssignmentService;

    public function __construct(RoleAssignmentService $roleAssignmentService)
    {
        $this->roleAssignmentService = $roleAssignmentService;
    }
    /**
     * Create a new role.
     *
     * @param  array  $data  Validated data.
     */
    public function createRole(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        Log::info('Creating a new role', $data);
        $role = Role::create($data);

        if (! empty($permissions)) {
            // ✅ Sử dụng RoleAssignmentService để auto clear cache
            $this->roleAssignmentService->syncPermissionsToRole($role, $permissions);
        }

        return $role;
    }

    /**
     * Update an existing role.
     *
     * @param  Role  $role  The role to update.
     * @param  array  $data  Validated data.
     */
    public function updateRole(Role $role, array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        Log::info("Updating role {$role->id}", $data);
        $role->update($data);

        // ✅ Sử dụng RoleAssignmentService để auto clear cache cho tất cả users có role này
        $this->roleAssignmentService->syncPermissionsToRole($role, $permissions);

        return $role;
    }

    /**
     * Delete a role.
     *
     * @param  Role  $role  The role to delete.
     */
    public function deleteRole(Role $role): void
    {
        Log::warning("Deleting role {$role->id}");
        $role->permissions()->detach();
        $role->delete();
    }
}
