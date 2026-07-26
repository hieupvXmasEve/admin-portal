<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\Actions\UpdateSystemConfigurationAction;
use App\Modules\Platform\Queries\GetSystemBrandingQuery;
use App\Modules\Platform\Support\SystemConfigurationStore;
use App\Services\DashboardChartsService;
use App\Services\DashboardStatsService;
use App\Shared\Contracts\Platform\StaffDashboardChartReader;
use App\Shared\Contracts\Platform\StaffDashboardStatsReader;
use App\Shared\Contracts\Platform\SystemConfigurationReader;
use App\Shared\Contracts\Platform\SystemConfigurationWriter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemConfigurationStore::class);
        $this->app->bind(SystemConfigurationReader::class, SystemConfigurationStore::class);
        $this->app->bind(SystemConfigurationWriter::class, UpdateSystemConfigurationAction::class);
        $this->app->bind(StaffDashboardStatsReader::class, DashboardStatsService::class);
        $this->app->bind(StaffDashboardChartReader::class, DashboardChartsService::class);
    }

    public function boot(): void
    {
        View::composer(['app', 'mcp.authorize'], function ($view): void {
            $view->with('systemBranding', app(GetSystemBrandingQuery::class)->handle());
        });

        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Route::prefix('api')
            ->middleware('api')
            ->name('api.')
            ->group(__DIR__.'/../routes/api.php');
    }
}
