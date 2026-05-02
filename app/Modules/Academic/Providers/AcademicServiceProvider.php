<?php

namespace App\Modules\Academic\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        // API routes are registered in routes/api/admin.php (web middleware, campus-scoped)
    }
}
