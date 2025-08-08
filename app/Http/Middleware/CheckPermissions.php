<?php

namespace App\Http\Middleware;

use App\Helpers\PermissionHelper;
use Closure;
use Illuminate\Http\Request;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Kiểm tra logic OR (mặc định)
        $logicType = 'any';

        // Nếu tham số đầu tiên là 'all', sử dụng logic AND
        if (isset($permissions[0]) && $permissions[0] === 'all') {
            $logicType = 'all';
            array_shift($permissions);
        }

        $hasPermission = $logicType === 'all'
            ? PermissionHelper::canAll($permissions)
            : PermissionHelper::canAny($permissions);

        if (! $hasPermission) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
