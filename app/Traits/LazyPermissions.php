<?php

namespace App\Traits;

use App\Services\PermissionService;
use Illuminate\Support\Facades\Cache;

trait LazyPermissions
{
    protected $loadedPermissions = [];
    protected $permissionService = null;

    /**
     * Kiểm tra quyền theo cách lazy loading
     */
    public function hasPermission(string $permission, int $campusId = null)
    {
        $campusId = $campusId ?? session('current_campus_id');
        $cacheKey = "campus_{$campusId}";

        if (!isset($this->loadedPermissions[$cacheKey])) {
            if (!$this->permissionService) {
                $this->permissionService = app(PermissionService::class);
            }

            $this->loadedPermissions[$cacheKey] = $this->permissionService->getUserPermissions($this, $campusId);
        }

        return in_array($permission, $this->loadedPermissions[$cacheKey]);
    }

    /**
     * Kiểm tra nhiều quyền cùng lúc (OR logic)
     */
    public function hasAnyPermission(array $permissions, int $campusId = null)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $campusId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kiểm tra nhiều quyền cùng lúc (AND logic)
     */
    public function hasAllPermissions(array $permissions, int $campusId = null)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission, $campusId)) {
                return false;
            }
        }

        return true;
    }
}
