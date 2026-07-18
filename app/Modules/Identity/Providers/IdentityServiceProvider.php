<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\IdentityContext;
use App\Modules\Identity\Support\EloquentGuardianAccessGrantReader;
use App\Modules\Identity\Support\EloquentGuardianAccessGrantWriter;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuardianAccessGrantReader::class, EloquentGuardianAccessGrantReader::class);
        $this->app->bind(GuardianAccessGrantWriter::class, EloquentGuardianAccessGrantWriter::class);

        $this->app->singleton(IdentityContext::class, function ($app) {
            return new IdentityContext;
        });
    }

    public function boot(): void
    {
        // IdentityContext is now self-initializing lazily.
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');
    }
}
