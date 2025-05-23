<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
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

        foreach ($permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                Gate::define($permission, function ($user) use ($permission) {
                    return session('permissions', collect())->contains($permission);
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
            return "<?php if(session('permissions', collect())->contains({$expression})): ?>";
        });

        Blade::directive('endhasPermission', function () {
            return '<?php endif; ?>';
        });
    }
}
