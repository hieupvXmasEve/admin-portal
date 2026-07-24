<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Providers;

use App\Modules\Engagement\Console\ProcessEventCompletions;
use App\Modules\Engagement\Console\ProcessFailedGoldRewards;
use App\Modules\Engagement\Console\SendEventReminders;
use App\Modules\Engagement\Support\EloquentFormPortalGateReader;
use App\Shared\Contracts\Engagement\FormPortalGateReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FormPortalGateReader::class, EloquentFormPortalGateReader::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessEventCompletions::class,
                ProcessFailedGoldRewards::class,
                SendEventReminders::class,
            ]);
        }
    }
}
