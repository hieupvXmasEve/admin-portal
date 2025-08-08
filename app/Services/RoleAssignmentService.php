<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Gán vai trò cho người dùng tại một campus.
     */
    public function assignRoleToUser(User $user, Role $role, int $campusId): void
    {
        DB::table('campus_user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $role->id, 'campus_id' => $campusId]
        );

        $this->permissionService->clearUserPermissionsCache($user);
    }

    /**
     * Gỡ vai trò của người dùng tại một campus.
     */
    public function removeRoleFromUser(User $user, Role $role, int $campusId): void
    {
        DB::table('campus_user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('campus_id', $campusId)
            ->delete();

        $this->permissionService->clearUserPermissionsCache($user);
    }

    /**
     * Thêm quyền cho một vai trò.
     */
    public function addPermissionToRole(Role $role, Permission $permission): void
    {
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->clearCacheForRole($role);
    }

    /**
     * Gỡ quyền khỏi một vai trò.
     */
    public function removePermissionFromRole(Role $role, Permission $permission): void
    {
        $role->permissions()->detach($permission->id);

        $this->clearCacheForRole($role);
    }

    /**
     * Xóa cache cho tất cả người dùng có một vai trò cụ thể.
     */
    protected function clearCacheForRole(Role $role): void
    {
        $userIds = DB::table('campus_user_roles')
            ->where('role_id', $role->id)
            ->distinct()
            ->pluck('user_id');

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->permissionService->clearUserPermissionsCache($user);
        }
    }
}
