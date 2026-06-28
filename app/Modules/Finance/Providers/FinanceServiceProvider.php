<?php

declare(strict_types=1);

namespace App\Modules\Finance\Providers;

use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Services\DngChecksumService;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use App\Modules\Finance\Dng\Services\DngWebhookService;
use App\Modules\Finance\Policies\FinanceChargePolicy;
use App\Modules\Finance\Services\DeferCaseService;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\AiFinanceMetricReader as ModuleAiFinanceMetricReader;
use App\Modules\Finance\Support\AiFinanceStudentProfileReader as ModuleAiFinanceStudentProfileReader;
use App\Modules\Finance\Support\HubStudentFinanceSummaryReader as ModuleHubStudentFinanceSummaryReader;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use App\Shared\Contracts\Finance\AiFinanceStudentProfileReader;
use App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader;
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
        $this->app->bind(HubStudentFinanceSummaryReader::class, ModuleHubStudentFinanceSummaryReader::class);

        // Register services as singletons
        $this->app->singleton(FinanceChargeService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(SettlementService::class);
        $this->app->singleton(InvoiceGenerationService::class);
        $this->app->singleton(DeferCaseService::class, function ($app) {
            return new DeferCaseService(
                $app->make(FinanceChargeService::class)
            );
        });

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

        // Explicit registration required: Laravel 13 auto-discovery does not
        // resolve policies in module namespaces (App\Modules\*) automatically.
        Gate::policy(FinanceCharge::class, FinanceChargePolicy::class);
    }
}
