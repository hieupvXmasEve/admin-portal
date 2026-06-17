<?php

use App\Modules\Finance\Http\Web\Admin\BatchStudioController;
use App\Modules\Finance\Http\Web\Admin\BillingInvoiceController;
use App\Modules\Finance\Http\Web\Admin\BillingOperationsController;
use App\Modules\Finance\Http\Web\Admin\BillingSettlementController;
use App\Modules\Finance\Http\Web\Admin\DngPaymentRequestController;
use App\Modules\Finance\Http\Web\Admin\DngWebhookEventController;
use App\Modules\Finance\Http\Web\Admin\DngWorklistController;
use App\Modules\Finance\Http\Web\Admin\EgcBlockResultsController;
use App\Modules\Finance\Http\Web\Admin\EgcCarryForwardController;
use App\Modules\Finance\Http\Web\Admin\EgcChargeGenerationController;
use App\Modules\Finance\Http\Web\Admin\EgcRetakeAdjustmentsController;
use App\Modules\Finance\Http\Web\Admin\FinanceAuditWorkspaceController;
use App\Modules\Finance\Http\Web\Admin\FinanceChargeController;
use App\Modules\Finance\Http\Web\Admin\FinanceCockpitController;
use App\Modules\Finance\Http\Web\Admin\FinanceGlobalSearchController;
use App\Modules\Finance\Http\Web\Admin\FinanceSemesterContextController;
use App\Modules\Finance\Http\Web\Admin\FinanceStudentOverviewController;
use App\Modules\Finance\Http\Web\Admin\FinanceStudentPaymentController;
use App\Modules\Finance\Http\Web\Admin\LifecycleDueExceptionController;
use App\Modules\Finance\Http\Web\Admin\LifecycleDueExceptionHistoryController;
use App\Modules\Finance\Http\Web\Admin\MajorChargeGenerationController;
use App\Modules\Finance\Http\Web\Admin\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->prefix('finance')->name('finance.')->group(function () {
    // Audit Workspace (read-only) — primary Finance Office entry
    Route::get('/audit', [FinanceAuditWorkspaceController::class, 'index'])
        ->middleware('can:view_finance_audit_workspace')
        ->name('audit.index');

    // Cockpit "Hôm nay"
    Route::get('/cockpit', [FinanceCockpitController::class, 'index'])
        ->middleware('can:view_finance_cockpit')
        ->name('cockpit.index');
    Route::get('/cockpit/queue/{queue}', [FinanceCockpitController::class, 'queueRows'])
        ->middleware('can:view_finance_cockpit')
        ->name('cockpit.queue-rows');
    Route::post('/cockpit/phase', [FinanceCockpitController::class, 'setPhase'])
        ->middleware('can:view_finance_cockpit')
        ->name('cockpit.phase');

    // EGC Operations
    Route::prefix('egc')->name('egc.')->group(function () {
        // EGC Charge Generation
        Route::prefix('charges')->name('charges.')->group(function () {
            Route::get('/', [EgcChargeGenerationController::class, 'index'])
                ->middleware('can:view_egc_finance_operations')
                ->name('index');
            Route::post('/', [EgcChargeGenerationController::class, 'store'])
                ->middleware('can:generate_egc_finance_charges')
                ->name('store');
        });

        // EGC Block Results
        Route::prefix('block-results')->name('block-results.')->group(function () {
            Route::get('/', [EgcBlockResultsController::class, 'index'])
                ->middleware('can:view_egc_block_results')
                ->name('index');
            Route::post('/sync', [EgcBlockResultsController::class, 'sync'])
                ->middleware('can:sync_egc_block_results')
                ->name('sync');
        });

        // EGC Retake Adjustments
        Route::prefix('retake-adjustments')->name('retake-adjustments.')->group(function () {
            Route::get('/', [EgcRetakeAdjustmentsController::class, 'index'])
                ->middleware('can:view_egc_retake_adjustments')
                ->name('index');
            Route::post('/', [EgcRetakeAdjustmentsController::class, 'store'])
                ->middleware('can:apply_egc_retake_adjustment')
                ->name('store');
            Route::post('/apply-major-entry-credit', [EgcRetakeAdjustmentsController::class, 'applyMajorEntryCredit'])
                ->middleware('can:apply_egc_retake_adjustment')
                ->name('apply-major-entry-credit');
        });

        Route::prefix('carry-forward')->name('carry-forward.')->group(function () {
            Route::get('/', [EgcCarryForwardController::class, 'index'])
                ->middleware('can:view_egc_retake_adjustments')
                ->name('index');
            Route::post('/', [EgcCarryForwardController::class, 'store'])
                ->middleware('can:apply_egc_retake_adjustment')
                ->name('store');
        });
    });

    // Major Tuition (HP) Charge Generation — for intake_course students
    Route::prefix('major')->name('major.')->group(function () {
        Route::prefix('charges')->name('charges.')->group(function () {
            Route::get('/', [MajorChargeGenerationController::class, 'index'])
                ->middleware('can:view_finance_charges')
                ->name('index');
            Route::post('/', [MajorChargeGenerationController::class, 'store'])
                ->middleware('can:create_finance_charges')
                ->name('store');
        });
    });

    // ... other finance routes ...

    // Operations
    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/dashboard', [BillingOperationsController::class, 'dashboard'])
            ->middleware('can:view_finance_operations_dashboard')
            ->name('dashboard');

        Route::get('/generate-charges', [BillingOperationsController::class, 'showGenerateCharges'])
            ->middleware('can:view_finance_operations_generate_charges')
            ->name('generate-charges');

        Route::get('/non-academic-charges-template', [BillingOperationsController::class, 'downloadNonAcademicChargesTemplate'])
            ->middleware('can:view_finance_operations_generate_charges')
            ->name('non-academic-charges-template');

        Route::get('/exceptions', [BillingOperationsController::class, 'exceptions'])
            ->middleware('can:view_finance_operations_exceptions')
            ->name('exceptions');

        Route::get('/due-calendar', [BillingOperationsController::class, 'dueCalendar'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('due-calendar');

        Route::get('/lifecycle-exceptions', [LifecycleDueExceptionController::class, 'index'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('lifecycle-exceptions');
        Route::get('/lifecycle-exception-history', [LifecycleDueExceptionHistoryController::class, 'index'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('lifecycle-exception-history');
        Route::post('/lifecycle-exceptions/{dngPaymentRequest}/acknowledge', [LifecycleDueExceptionController::class, 'acknowledge'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('lifecycle-exceptions.acknowledge');
        Route::post('/lifecycle-exceptions/{dngPaymentRequest}/resolve', [LifecycleDueExceptionController::class, 'resolve'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('lifecycle-exceptions.resolve');

        Route::get('/settlement', [BillingSettlementController::class, 'index'])
            ->middleware('can:allocate_finance_payment')
            ->name('settlement.index');
        Route::post('/settlement/apply', [BillingSettlementController::class, 'apply'])
            ->middleware('can:allocate_finance_payment')
            ->name('settlement.apply');
        // /finance/operations/batch-dng removed — replaced by Batch Studio DNG below
        // Route kept commented for redirect reference only

        // Legacy DNG Worklist URL — compatibility redirect to Batch Studio DNG wizard.
        Route::get('/dng-worklist', [DngWorklistController::class, 'index'])
            ->middleware('can:view_finance_batch_studio')
            ->name('dng-worklist');
        Route::post('/dng-worklist', [DngWorklistController::class, 'store'])
            ->middleware('can:create_finance_payments')
            ->name('dng-worklist.store');
    });

    // Invoices

    // Invoices
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [BillingInvoiceController::class, 'index'])
            ->middleware('can:view_finance_invoices')
            ->name('index');
        Route::get('/export', [BillingInvoiceController::class, 'export'])
            ->middleware('can:view_finance_export_invoices')
            ->name('export');
        Route::get('/{invoice}', [BillingInvoiceController::class, 'show'])
            ->middleware('can:view_finance_invoices')
            ->name('show');
        Route::post('/{invoice}/lines/{line}/void', [BillingInvoiceController::class, 'voidLine'])
            ->middleware('can:void_finance_charges')
            ->name('lines.void');
    });
    // Student 360 read-only shell (search destination)
    Route::get('/students/{student}', [FinanceStudentOverviewController::class, 'show'])
        ->middleware('can:view_finance_student_overview')
        ->name('students.overview');
    Route::post('/students/{student}/payments', [FinanceStudentPaymentController::class, 'store'])
        ->middleware('can:create_finance_payments')
        ->name('students.payments.store');

    // Global finance search (JSON) for the ⌘K palette
    Route::get('/search', [FinanceGlobalSearchController::class, 'search'])
        ->middleware('can:view_finance_student_overview')
        ->name('search');

    // Operator semester selection (single source; reflected via shared prop)
    Route::post('/semester-context', [FinanceSemesterContextController::class, 'update'])
        ->name('semester-context.update');

    // =====================
    // Finance Charges
    // =====================
    Route::get('/students/{student}/charges', [FinanceChargeController::class, 'studentCharges'])
        ->middleware('can:view_finance_charges')
        ->name('students.charges');

    Route::prefix('charges')->name('charges.')->group(function () {
        Route::get('/', [FinanceChargeController::class, 'index'])
            ->middleware('can:view_finance_charges')
            ->name('index');
        Route::get('/create', [FinanceChargeController::class, 'create'])
            ->middleware('can:create_finance_charges')
            ->name('create');
        Route::post('/', [FinanceChargeController::class, 'store'])
            ->middleware('can:create_finance_charges')
            ->name('store');
        Route::get('/{charge}', [FinanceChargeController::class, 'show'])
            ->middleware('can:view_finance_charges')
            ->name('show');
        Route::post('/{charge}/void', [FinanceChargeController::class, 'void'])
            ->middleware('can:void_finance_charges')
            ->name('void');
        Route::patch('/{charge}/description', [FinanceChargeController::class, 'updateDescription'])
            ->middleware('can:create_finance_charges')
            ->name('update-description');
        Route::post('/{charge}/installments/split', [FinanceChargeController::class, 'splitInstallments'])
            ->middleware('can:split_installment_finance_charges')
            ->name('installments.split');
        Route::post('/{charge}/installments/{installment}/retry-push', [FinanceChargeController::class, 'retryPushInstallment'])
            ->middleware('can:split_installment_finance_charges')
            ->name('installments.retry-push');
    });

    // =====================
    // Payments
    // =====================
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('can:view_finance_payments')
            ->name('index');
        // /finance/payments/create removed — DNG creation unified in Batch Studio DNG
        // /finance/payments/{student}/dng-data removed — no longer needed
        Route::get('/auto-allocate', [PaymentController::class, 'showAutoAllocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('auto-allocate.show');
        Route::post('/auto-allocate/preview', [PaymentController::class, 'previewAutoAllocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('auto-allocate.preview');
        Route::post('/auto-allocate', [PaymentController::class, 'autoAllocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('auto-allocate');
        Route::get('/{payment}/allocate-preview', [FinanceStudentPaymentController::class, 'allocatePreview'])
            ->middleware('can:allocate_finance_payment')
            ->name('allocate-preview');
        Route::get('/{payment}', [PaymentController::class, 'show'])
            ->middleware('can:view_finance_payment_details')
            ->name('show');
        Route::post('/{payment}/allocate', [PaymentController::class, 'allocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('allocate');
    });

    Route::prefix('dng')->name('dng.')->group(function () {
        Route::prefix('payment-requests')->name('payment-requests.')->group(function () {
            Route::get('/', [DngPaymentRequestController::class, 'index'])
                ->middleware('can:view_finance_dng_payment_requests')
                ->name('index');
            Route::get('/{dngPaymentRequest}', [DngPaymentRequestController::class, 'show'])
                ->middleware('can:view_finance_dng_payment_requests')
                ->name('show');
            Route::get('/{dngPaymentRequest}/cancel-impact', [DngPaymentRequestController::class, 'cancelImpact'])
                ->middleware('can:create_finance_payments')
                ->name('cancel-impact');
            Route::post('/{dngPaymentRequest}/cancel-reviewed', [DngPaymentRequestController::class, 'cancelReviewed'])
                ->middleware('can:create_finance_payments')
                ->name('cancel-reviewed');
            Route::post('/{dngPaymentRequest}/cancel', [DngPaymentRequestController::class, 'cancel'])
                ->middleware('can:create_finance_payments')
                ->name('cancel');
        });

        Route::prefix('webhook-events')->name('webhook-events.')->group(function () {
            Route::get('/', [DngWebhookEventController::class, 'index'])
                ->middleware('can:view_finance_dng_webhook_events')
                ->name('index');
            Route::get('/{dngWebhookEvent}', [DngWebhookEventController::class, 'show'])
                ->middleware('can:view_finance_dng_webhook_events')
                ->name('show');
            Route::post('/{dngWebhookEvent}/retry', [DngWebhookEventController::class, 'retry'])
                ->middleware('can:create_finance_payments')
                ->name('retry');
        });
    });

    // Retake Course Charge routes removed — absorbed by Batch Studio DNG (fee_type=HL)
    // See: DngWorklistController + CreateBatchDngFromChargesAction (auto-creates charges for approved registrations)

    Route::prefix('batch-studio')->name('batch-studio.')->group(function () {
        Route::get('/', [BatchStudioController::class, 'hub'])
            ->middleware('can:view_finance_batch_studio')->name('hub');
        Route::get('/charges', [BatchStudioController::class, 'charges'])
            ->middleware('can:view_finance_batch_studio')->name('charges');
        Route::post('/charges', [BatchStudioController::class, 'commitCharges'])
            ->name('charges.commit');
        Route::get('/dng', [BatchStudioController::class, 'dng'])
            ->middleware('can:view_finance_batch_studio')->name('dng');
        Route::post('/dng', [BatchStudioController::class, 'commitDng'])
            ->middleware('can:create_finance_payments')->name('dng.commit');
        Route::get('/reminders', [BatchStudioController::class, 'reminders'])
            ->middleware('can:view_finance_batch_studio')->name('reminders');
        Route::post('/reminders', [BatchStudioController::class, 'commitReminders'])
            ->middleware('can:view_finance_operations_due_calendar')->name('reminders.commit');
    });
});
