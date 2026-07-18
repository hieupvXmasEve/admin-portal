<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\IdentityContext;
use App\Modules\Identity\Support\EloquentGuardianAccessGrantReader;
use App\Modules\Identity\Support\EloquentGuardianAccessGrantWriter;
use App\Modules\Identity\Support\EloquentLecturerAccessGrantReader;
use App\Modules\Identity\Support\EloquentLecturerAccessGrantWriter;
use App\Modules\Identity\Support\EloquentStudentAccessWriter;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\Identity\LecturerAccessGrantReader;
use App\Shared\Contracts\Identity\LecturerAccessGrantWriter;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuardianAccessGrantReader::class, EloquentGuardianAccessGrantReader::class);
        $this->app->bind(GuardianAccessGrantWriter::class, EloquentGuardianAccessGrantWriter::class);
        $this->app->bind(LecturerAccessGrantReader::class, EloquentLecturerAccessGrantReader::class);
        $this->app->bind(LecturerAccessGrantWriter::class, EloquentLecturerAccessGrantWriter::class);
        $this->app->bind(StudentAccessWriter::class, EloquentStudentAccessWriter::class);

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
