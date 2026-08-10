<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Providers;

use App\Modules\Admissions\Console\SyncCrmApplicationsCommand;
use App\Modules\Admissions\Support\EloquentApplicationProgramMappingReader;
use App\Shared\Contracts\Admissions\ApplicationProgramMappingReader;
use Illuminate\Support\ServiceProvider;

final class AdmissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationProgramMappingReader::class, EloquentApplicationProgramMappingReader::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCrmApplicationsCommand::class,
            ]);
        }
    }
}
