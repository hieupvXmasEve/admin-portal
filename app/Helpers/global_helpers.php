<?php

use App\Helpers\PermissionHelper;

if (! function_exists('can_permission')) {
    function can_permission($permission)
    {
        return PermissionHelper::can($permission);
    }
}

if (! function_exists('can_any_permission')) {
    function can_any_permission(array $permissions)
    {
        return PermissionHelper::canAny($permissions);
    }
}
