<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\IdentityContext;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdentityContext::class, function ($app) {
            return new IdentityContext();
        });
    }

    public function boot(): void
    {
        // IdentityContext is now self-initializing lazily.
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        
        \Illuminate\Support\Facades\Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__ . '/../routes/api.php');
    }
}
