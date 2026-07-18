<?php

declare(strict_types=1);

namespace App\Modules\Academic\Providers;

use App\Models\CourseRegistration;
use App\Modules\Academic\Actions\CompleteFinanceCancellationOperationAction;
use App\Modules\Academic\Actions\SyncPaidExamResitAttemptsAction;
use App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction;
use App\Modules\Academic\Catalog\Support\EloquentCourseOfferingCatalogReader;
use App\Modules\Academic\Catalog\Support\SemesterAcademicPeriodReader;
use App\Modules\Academic\Delivery\Support\EloquentAcademicSpaceOccupancyReader;
use App\Modules\Academic\Observers\CourseRegistrationObserver;
use App\Modules\Academic\Support\AcademicFinanceChargeSourceGateway as ModuleAcademicFinanceChargeSourceGateway;
use App\Modules\Academic\Support\AiAcademicEntitySearchReader as ModuleAiAcademicEntitySearchReader;
use App\Modules\Academic\Support\AiAcademicMetricReader as ModuleAiAcademicMetricReader;
use App\Modules\Academic\Support\AiAcademicStudentProfileReader as ModuleAiAcademicStudentProfileReader;
use App\Modules\Academic\Support\CampusBuildingCountReader as ModuleCampusBuildingCountReader;
use App\Modules\Academic\Support\StudentLifecycleStatusReader as ModuleStudentLifecycleStatusReader;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\AcademicSpaceOccupancyReader;
use App\Shared\Contracts\Academic\AiAcademicEntitySearchReader;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use App\Shared\Contracts\Academic\AiAcademicStudentProfileReader;
use App\Shared\Contracts\Academic\CampusBuildingCountReader;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AcademicPeriodReader::class, SemesterAcademicPeriodReader::class);
        $this->app->bind(AcademicSpaceOccupancyReader::class, EloquentAcademicSpaceOccupancyReader::class);
        $this->app->bind(CourseOfferingCatalogReader::class, EloquentCourseOfferingCatalogReader::class);
        $this->app->bind(AiAcademicMetricReader::class, ModuleAiAcademicMetricReader::class);
        $this->app->bind(AiAcademicEntitySearchReader::class, ModuleAiAcademicEntitySearchReader::class);
        $this->app->bind(AiAcademicStudentProfileReader::class, ModuleAiAcademicStudentProfileReader::class);
        $this->app->bind(CampusBuildingCountReader::class, ModuleCampusBuildingCountReader::class);
        $this->app->bind(AcademicFinanceChargeSourceGateway::class, ModuleAcademicFinanceChargeSourceGateway::class);
        $this->app->bind(StudentLifecycleStatusReader::class, ModuleStudentLifecycleStatusReader::class);
        $this->app->bind(RetakeRegistrationPaymentSyncer::class, SyncPaidRetakeRegistrationsAction::class);
        $this->app->bind(ExamResitAttemptPaymentSyncer::class, SyncPaidExamResitAttemptsAction::class);
        $this->app->bind(FinanceCancellationCompletionContract::class, CompleteFinanceCancellationOperationAction::class);
    }

    public function boot(): void
    {
        CourseRegistration::observe(CourseRegistrationObserver::class);

        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        // API routes are registered in routes/api/admin.php (web middleware, campus-scoped)
    }
}
