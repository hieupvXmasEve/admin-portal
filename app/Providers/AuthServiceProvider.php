<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
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
        $this->registerPolicies();
        Gate::define('view_user', function (User $user) {
            return $user->hasPermission('view_user', session('current_campus_id'));
        });
        Gate::define('add_user', function (User $user) {
            return $user->hasPermission('add_user', session('current_campus_id'));
        });
    }
}
