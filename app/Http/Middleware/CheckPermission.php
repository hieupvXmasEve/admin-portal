<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // If module is provided, use it directly
        if ($module) {
            $permission = $this->getPermissionForAction($module, $request->route()->getActionMethod());

            if ($permission && ! Gate::allows($permission)) {
                abort(403, 'Unauthorized action.');
            }

            return $next($request);
        }

        // Auto-detect module from route
        $routeName = $request->route()->getName();
        $detectedModule = $this->detectModuleFromRoute($routeName);

        if ($detectedModule) {
            $permission = $this->getPermissionForAction($detectedModule, $request->route()->getActionMethod());

            if ($permission && ! Gate::allows($permission)) {
                abort(403, 'Unauthorized action.');
            }
        }

        return $next($request);
    }

    /**
     * Detect module from route name
     */
    private function detectModuleFromRoute(string $routeName): ?string
    {
        $permissions = config('permission.access', []);

        foreach (array_keys($permissions) as $module) {
            if (str_contains($routeName, $module) || str_contains($routeName, rtrim($module, 's'))) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Get permission based on HTTP method and action
     */
    private function getPermissionForAction(string $module, string $action): ?string
    {
        $permissions = config("permission.access.{$module}", []);

        // Map controller actions to permission types
        $actionMap = [
            'index' => 'view',
            'show' => 'view',
            'create' => 'add',
            'store' => 'add',
            'edit' => 'edit',
            'update' => 'edit',
            'destroy' => 'delete',
        ];

        $permissionType = $actionMap[$action] ?? 'view';
        $permissionKey = "{$permissionType}_".rtrim($module, 's');

        return $permissions[$permissionKey] ?? null;
    }
}
