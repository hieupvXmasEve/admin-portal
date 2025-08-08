<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Lấy danh sách quyền của người dùng tại một campus cụ thể
     */
    public function getUserPermissions(User $user, ?int $campusId = null)
    {
        $cacheKey = "user_permissions_{$user->id}_campus_".($campusId ?? 'all');

        return Cache::remember($cacheKey, now()->addDay(), function () use ($user, $campusId) {
            $query = DB::table('permissions')
                ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->join('campus_user_roles', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
                ->where('campus_user_roles.user_id', $user->id);

            if ($campusId) {
                $query->where('campus_user_roles.campus_id', $campusId);
            }

            return $query->select('permissions.code')
                ->distinct()
                ->pluck('code')
                ->toArray();
        });
    }

    /**
     * Xóa cache quyền của người dùng
     */
    public function clearUserPermissionsCache(User $user)
    {
        Cache::forget("user_permissions_{$user->id}_campus_all");

        // Xóa cache cho từng campus
        $campusIds = DB::table('campus_user_roles')
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('campus_id');

        foreach ($campusIds as $campusId) {
            Cache::forget("user_permissions_{$user->id}_campus_{$campusId}");
        }
    }
}
