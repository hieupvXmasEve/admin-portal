<?php

declare(strict_types=1);

namespace App\Modules\Academic\Providers;

use App\Models\CourseRegistration;
use App\Modules\Academic\Actions\SyncPaidExamResitAttemptsAction;
use App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction;
use App\Modules\Academic\Observers\CourseRegistrationObserver;
use App\Modules\Academic\Support\AiAcademicMetricReader as ModuleAiAcademicMetricReader;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiAcademicMetricReader::class, ModuleAiAcademicMetricReader::class);
        $this->app->bind(RetakeRegistrationPaymentSyncer::class, SyncPaidRetakeRegistrationsAction::class);
        $this->app->bind(ExamResitAttemptPaymentSyncer::class, SyncPaidExamResitAttemptsAction::class);
    }

    public function boot(): void
    {
        CourseRegistration::observe(CourseRegistrationObserver::class);

        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        // API routes are registered in routes/api/admin.php (web middleware, campus-scoped)
    }
}
