<?php

namespace App\Modules\Academic\Providers;

use App\Models\CourseRegistration;
use App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction;
use App\Modules\Academic\Observers\CourseRegistrationObserver;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RetakeRegistrationPaymentSyncer::class, SyncPaidRetakeRegistrationsAction::class);
    }

    public function boot(): void
    {
        CourseRegistration::observe(CourseRegistrationObserver::class);

        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        // API routes are registered in routes/api/admin.php (web middleware, campus-scoped)
    }
}
