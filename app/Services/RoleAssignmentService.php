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
     * Gán nhiều vai trò cho người dùng tại một campus
     */
    public function assignMultipleRolesToUser(User $user, array $roleIds, int $campusId): void
    {
        // Xóa tất cả vai trò cũ của user tại campus này
        DB::table('campus_user_roles')
            ->where('user_id', $user->id)
            ->where('campus_id', $campusId)
            ->delete();

        // Gán các vai trò mới
        $data = [];
        foreach ($roleIds as $roleId) {
            $data[] = [
                'user_id' => $user->id,
                'role_id' => $roleId,
                'campus_id' => $campusId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($data)) {
            DB::table('campus_user_roles')->insert($data);
        }

        $this->permissionService->clearUserPermissionsCache($user);
    }

    /**
     * Gán nhiều quyền cho một vai trò
     */
    public function syncPermissionsToRole(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);

        $this->clearCacheForRole($role);
    }

    /**
     * Lấy tất cả vai trò của user tại một campus
     */
    public function getUserRolesAtCampus(User $user, int $campusId): \Illuminate\Support\Collection
    {
        return DB::table('campus_user_roles')
            ->join('roles', 'campus_user_roles.role_id', '=', 'roles.id')
            ->where('campus_user_roles.user_id', $user->id)
            ->where('campus_user_roles.campus_id', $campusId)
            ->select('roles.*')
            ->get();
    }

    /**
     * Lấy tất cả user có vai trò cụ thể tại một campus
     */
    public function getUsersByRoleAtCampus(Role $role, int $campusId): \Illuminate\Support\Collection
    {
        return DB::table('campus_user_roles')
            ->join('users', 'campus_user_roles.user_id', '=', 'users.id')
            ->where('campus_user_roles.role_id', $role->id)
            ->where('campus_user_roles.campus_id', $campusId)
            ->select('users.*')
            ->get();
    }

    /**
     * Xóa cache cho tất cả người dùng có một vai trò cụ thể
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
