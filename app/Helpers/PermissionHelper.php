<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PermissionHelper
{
    /**
     * Kiểm tra quyền với campus hiện tại
     */
    public static function can($permission)
    {
        return Gate::allows($permission);
    }

    /**
     * Kiểm tra quyền với campus cụ thể
     */
    public static function canAtCampus($permission, $campusId)
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission($permission, $campusId);
    }

    /**
     * Kiểm tra nhiều quyền (OR logic)
     */
    public static function canAny(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kiểm tra nhiều quyền (AND logic)
     */
    public static function canAll(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (! self::can($permission)) {
                return false;
            }
        }

        return true;
    }
}
