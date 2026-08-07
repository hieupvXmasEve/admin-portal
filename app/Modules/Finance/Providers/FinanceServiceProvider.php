<?php

declare(strict_types=1);

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Actions\ApplyScholarshipSemesterAdjustmentAction;
use App\Modules\Finance\Actions\ApplyStudentLifecycleDeferAction;
use App\Modules\Finance\Actions\AttachStudentLifecycleFinanceEvidenceAction;
use App\Modules\Finance\Actions\CancelFinanceObligationAction;
use App\Modules\Finance\Actions\Egc\SyncEgcBlockResultsAction;
use App\Modules\Finance\Actions\RequestFinanceCancellationOperationAction;
use App\Modules\Finance\Actions\ResolveFinanceCancellationChargeStateAction;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use App\Modules\Finance\Dng\Services\DngWebhookService;
use App\Modules\Finance\Listeners\ProvisionBillingAccountForRegisteredStudent;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Policies\DngReceiptExceptionPolicy;
use App\Modules\Finance\Policies\FinanceChargePolicy;
use App\Modules\Finance\Queries\GetStudentFeeSummaryQuery;
use App\Modules\Finance\Queries\TuitionChargeExistenceQuery;
use App\Modules\Finance\Queries\GetStudentPortalFinanceSummaryQuery;
use App\Modules\Finance\Queries\PreviewScholarshipAdjustmentQuery;
use App\Modules\Finance\Queries\StudentLifecycleFinanceQuery;
use App\Modules\Finance\Services\DeferCaseService;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\AiFinanceMetricReader as ModuleAiFinanceMetricReader;
use App\Modules\Finance\Support\AiFinanceStudentProfileReader as ModuleAiFinanceStudentProfileReader;
use App\Modules\Finance\Support\EloquentBillingAccountRollbackWriter;
use App\Modules\Finance\Support\EloquentDngPaymentNotificationContextReader;
use App\Modules\Finance\Support\FinanceIntakeRouter;
use App\Modules\Finance\Support\HubStudentFinanceSummaryReader as ModuleHubStudentFinanceSummaryReader;
use App\Modules\Finance\Support\ObligationLedgerSettlementReader;
use App\Modules\Finance\Support\SettlementPosition\CurrentPayableSettlementPositionReader;
use App\Modules\Finance\Support\ZeroTuitionTermLookup;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use App\Shared\Contracts\Finance\AiFinanceStudentProfileReader;
use App\Shared\Contracts\Finance\BillingAccountRollbackWriter;
use App\Shared\Contracts\Finance\DngPaymentNotificationContextReader;
use App\Shared\Contracts\Finance\EgcBlockResultReconciler;
use App\Shared\Contracts\Finance\FinanceCancellationChargeStateReader;
use App\Shared\Contracts\Finance\FinanceCancellationOperationRequestContract;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\Finance\FinanceObligationCancellationContract;
use App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentPreviewReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\Finance\StudentFeeSummaryReader;
use App\Shared\Contracts\Finance\TuitionChargeExistenceReader;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceCommand;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceEvidenceWriter;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;
use App\Shared\Contracts\Finance\StudentPortalFinanceSummaryReader;
use App\Shared\Contracts\StudentRegistry\StudentRegistered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AiFinanceMetricReader::class, ModuleAiFinanceMetricReader::class);
        $this->app->bind(AiFinanceStudentProfileReader::class, ModuleAiFinanceStudentProfileReader::class);
        $this->app->bind(BillingAccountRollbackWriter::class, EloquentBillingAccountRollbackWriter::class);
        $this->app->bind(DngPaymentNotificationContextReader::class, EloquentDngPaymentNotificationContextReader::class);
        $this->app->bind(EgcBlockResultReconciler::class, SyncEgcBlockResultsAction::class);
        $this->app->bind(FinanceIntakeContract::class, FinanceIntakeRouter::class);
        $this->app->bind(ScholarshipAdjustmentContract::class, ApplyScholarshipSemesterAdjustmentAction::class);
        $this->app->bind(ScholarshipAdjustmentPreviewReader::class, PreviewScholarshipAdjustmentQuery::class);
        $this->app->bind(FinanceCancellationOperationRequestContract::class, RequestFinanceCancellationOperationAction::class);
        $this->app->bind(FinanceCancellationChargeStateReader::class, ResolveFinanceCancellationChargeStateAction::class);
        $this->app->bind(FinanceObligationCancellationContract::class, CancelFinanceObligationAction::class);
        $this->app->bind(HubStudentFinanceSummaryReader::class, ModuleHubStudentFinanceSummaryReader::class);
        $this->app->bind(ObligationSettlementReader::class, ObligationLedgerSettlementReader::class);
        $this->app->bind(SettlementPositionReader::class, CurrentPayableSettlementPositionReader::class);
        $this->app->bind(StudentFeeSummaryReader::class, GetStudentFeeSummaryQuery::class);
        $this->app->bind(TuitionChargeExistenceReader::class, TuitionChargeExistenceQuery::class);
        $this->app->bind(StudentPortalFinanceSummaryReader::class, GetStudentPortalFinanceSummaryQuery::class);
        $this->app->bind(StudentLifecycleFinanceCommand::class, ApplyStudentLifecycleDeferAction::class);
        $this->app->bind(
            StudentLifecycleFinanceEvidenceWriter::class,
            AttachStudentLifecycleFinanceEvidenceAction::class,
        );
        $this->app->bind(StudentLifecycleFinanceReader::class, StudentLifecycleFinanceQuery::class);
        $this->app->scoped(ZeroTuitionTermLookup::class);

        // Register services as singletons
        $this->app->singleton(FinanceChargeService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(SettlementService::class);
        $this->app->singleton(InvoiceGenerationService::class);
        $this->app->singleton(DeferCaseService::class);

        // DNG payment gateway services
        $this->app->singleton(DngChecksumService::class);
        $this->app->singleton(DngClient::class);
        $this->app->singleton(DngPaymentService::class);
        $this->app->singleton(DngWebhookService::class);
        $this->app->singleton(DngReconciliationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load routes if they exist
        $routesPath = __DIR__.'/../routes';

        if (file_exists($routesPath.'/web.php')) {
            $this->loadRoutesFrom($routesPath.'/web.php');
        }

        if (file_exists($routesPath.'/api.php')) {
            $this->loadRoutesFrom($routesPath.'/api.php');
        }

        Event::listen(StudentRegistered::class, ProvisionBillingAccountForRegisteredStudent::class);

        // Explicit registration required: Laravel 13 auto-discovery does not
        // resolve policies in module namespaces (App\Modules\*) automatically.
        Gate::policy(FinanceCharge::class, FinanceChargePolicy::class);
        Gate::policy(DngReceiptException::class, DngReceiptExceptionPolicy::class);
    }
}
