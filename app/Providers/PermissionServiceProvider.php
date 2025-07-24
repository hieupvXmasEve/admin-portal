<?php

namespace App\Providers;

use App\Services\PermissionService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
        $this->registerBladeDirectives();
    }

    /**
     * Register all permission gates from configuration
     */
    private function registerPermissionGates(): void
    {
        $permissions = config('permission.access', []);
        Log::info('Permissions: ' . json_encode($permissions));
        foreach ($permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                Gate::define($permission, function ($user) use ($permission) {
                    // Lấy campus_id hiện tại từ session
                    $currentCampusId = session('current_campus_id');

                    // Sử dụng service để lấy quyền từ cache
                    $permissionService = app(PermissionService::class);
                    $permissions = $permissionService->getUserPermissions($user, $currentCampusId);
                    Log::info('Permissions: ' . json_encode($permissions));
                    return in_array($permission, $permissions);
                });
            }
        }
    }

    /**
     * Register custom Blade directives for permissions
     */
    private function registerBladeDirectives(): void
    {
        // @canPermission('module', 'action')
        Blade::directive('canPermission', function ($expression) {
            return "<?php if(Gate::allows(\\App\\Helpers\\RoutePermissionHelper::getPermissionName({$expression}))): ?>";
        });

        Blade::directive('endcanPermission', function () {
            return '<?php endif; ?>';
        });

        // @hasPermission('permission_code')
        Blade::directive('hasPermission', function ($expression) {
            $expression = trim($expression, "'\"");
            return "<?php if(auth()->check() && auth()->user()->hasPermission('{$expression}')): ?>";
        });

        Blade::directive('endhasPermission', function () {
            return '<?php endif; ?>';
        });
    }
}
