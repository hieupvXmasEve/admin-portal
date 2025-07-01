<?php

namespace App\Providers;

use App\Helpers\PermissionHelper;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Đăng ký helper function toàn cục
        if (!function_exists('can_permission')) {
            function can_permission($permission)
            {
                return PermissionHelper::can($permission);
            }
        }

        if (!function_exists('can_any_permission')) {
            function can_any_permission(array $permissions)
            {
                return PermissionHelper::canAny($permissions);
            }
        }
    }
}
