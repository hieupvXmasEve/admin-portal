<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Route;

class RoutePermissionHelper
{
    /**
     * Apply CRUD permissions to a resource route group
     */
    public static function resourceWithPermissions(string $prefix, string $controller, string $module, array $options = []): void
    {
        $permissions = config("permission.access.{$module}", []);
        $moduleSingular = rtrim($module, 's');

        Route::prefix($prefix)->group(function () use ($controller, $permissions, $moduleSingular, $options) {
            // Index route
            if (isset($permissions["view_{$moduleSingular}"])) {
                Route::get('/', [$controller, 'index'])
                    ->middleware("can:view_{$moduleSingular}")
                    ->name("{$moduleSingular}.index");
            }

            // Create routes
            if (isset($permissions["add_{$moduleSingular}"])) {
                Route::get('/add', [$controller, 'create'])
                    ->middleware("can:add_{$moduleSingular}")
                    ->name("{$moduleSingular}.create");

                Route::post('/', [$controller, 'store'])
                    ->middleware("can:add_{$moduleSingular}")
                    ->name("{$moduleSingular}.store");
            }

            // Edit routes
            if (isset($permissions["edit_{$moduleSingular}"])) {
                Route::get('/edit/{{$moduleSingular}}', [$controller, 'edit'])
                    ->middleware("can:edit_{$moduleSingular}")
                    ->name("{$moduleSingular}.edit");

                Route::put('/{{$moduleSingular}}', [$controller, 'update'])
                    ->middleware("can:edit_{$moduleSingular}")
                    ->name("{$moduleSingular}.update");
            }

            // Delete route
            if (isset($permissions["delete_{$moduleSingular}"])) {
                Route::delete('/{{$moduleSingular}}', [$controller, 'destroy'])
                    ->middleware("can:delete_{$moduleSingular}")
                    ->name("{$moduleSingular}.destroy");
            }

            // Show route (if view permission exists)
            if (isset($permissions["view_{$moduleSingular}"])) {
                Route::get('/{{$moduleSingular}}', [$controller, 'show'])
                    ->middleware("can:view_{$moduleSingular}")
                    ->name("{$moduleSingular}.show");
            }

            // Additional custom routes
            if (isset($options['additional'])) {
                foreach ($options['additional'] as $route) {
                    $method = $route['method'] ?? 'get';
                    $uri = $route['uri'];
                    $action = $route['action'];
                    $permission = $route['permission'] ?? null;
                    $name = $route['name'] ?? null;

                    $routeInstance = Route::$method($uri, [$controller, $action]);

                    if ($permission && isset($permissions[$permission])) {
                        $routeInstance->middleware("can:{$permission}");
                    }

                    if ($name) {
                        $routeInstance->name($name);
                    }
                }
            }
        });
    }

    /**
     * Apply permission middleware to a single route
     */
    public static function withPermission(string $module, string $action): string
    {
        $permissions = config("permission.access.{$module}", []);
        $moduleSingular = rtrim($module, 's');

        $actionMap = [
            'view' => "view_{$moduleSingular}",
            'add' => "add_{$moduleSingular}",
            'edit' => "edit_{$moduleSingular}",
            'delete' => "delete_{$moduleSingular}",
        ];

        $permission = $actionMap[$action] ?? null;

        if ($permission && isset($permissions[$permission])) {
            return "can:{$permission}";
        }

        return '';
    }

    /**
     * Get all permissions for a module
     */
    public static function getModulePermissions(string $module): array
    {
        return config("permission.access.{$module}", []);
    }

    /**
     * Get permission name for a module and action (used by Blade directives)
     */
    public static function getPermissionName(string $module, string $action): string
    {
        $permissions = config("permission.access.{$module}", []);
        $moduleSingular = rtrim($module, 's');

        $actionMap = [
            'view' => "view_{$moduleSingular}",
            'add' => "add_{$moduleSingular}",
            'edit' => "edit_{$moduleSingular}",
            'delete' => "delete_{$moduleSingular}",
        ];

        $permission = $actionMap[$action] ?? null;

        return $permission && isset($permissions[$permission]) ? $permission : '';
    }
}
