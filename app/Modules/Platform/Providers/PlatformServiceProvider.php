<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\Actions\UpdateSystemConfigurationAction;
use App\Modules\Platform\Support\SystemConfigurationStore;
use App\Shared\Contracts\Platform\SystemConfigurationReader;
use App\Shared\Contracts\Platform\SystemConfigurationWriter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemConfigurationStore::class);
        $this->app->bind(SystemConfigurationReader::class, SystemConfigurationStore::class);
        $this->app->bind(SystemConfigurationWriter::class, UpdateSystemConfigurationAction::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Route::prefix('api')
            ->middleware('api')
            ->name('api.')
            ->group(__DIR__.'/../routes/api.php');
    }
}
