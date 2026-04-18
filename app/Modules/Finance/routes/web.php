<?php

use App\Modules\Finance\Http\Web\Admin\BatchDngPageController;
use App\Modules\Finance\Http\Web\Admin\BillingInvoiceController;
use App\Modules\Finance\Http\Web\Admin\BillingOperationsController;
use App\Modules\Finance\Http\Web\Admin\BillingSettlementController;
use App\Modules\Finance\Http\Web\Admin\DngPaymentRequestController;
use App\Modules\Finance\Http\Web\Admin\DngWebhookEventController;
use App\Modules\Finance\Http\Web\Admin\FinanceChargeController;
use App\Modules\Finance\Http\Web\Admin\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->prefix('finance')->name('finance.')->group(function () {
    // ... other finance routes ...

    // Operations
    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/dashboard', [BillingOperationsController::class, 'dashboard'])
            ->middleware('can:view_finance_operations_dashboard')
            ->name('dashboard');

        Route::get('/generate-charges', [BillingOperationsController::class, 'showGenerateCharges'])
            ->middleware('can:view_finance_operations_generate_charges')
            ->name('generate-charges');

        Route::get('/exceptions', [BillingOperationsController::class, 'exceptions'])
            ->middleware('can:view_finance_operations_exceptions')
            ->name('exceptions');

        Route::get('/due-calendar', [BillingOperationsController::class, 'dueCalendar'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('due-calendar');

        Route::get('/settlement', [BillingSettlementController::class, 'index'])
            ->middleware('can:allocate_finance_payment')
            ->name('settlement.index');
        Route::post('/settlement/apply', [BillingSettlementController::class, 'apply'])
            ->middleware('can:allocate_finance_payment')
            ->name('settlement.apply');
        Route::get('/batch-dng', [BatchDngPageController::class, 'show'])
            ->middleware('can:create_finance_payments')
            ->name('batch-dng');
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
    });

    // =====================
    // Payments
    // =====================
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('can:view_finance_payments')
            ->name('index');
        Route::get('/create', [PaymentController::class, 'create'])
            ->middleware('can:create_finance_payments')
            ->name('create');
        Route::get('/{student}/dng-data', [PaymentController::class, 'getStudentDngData'])
            ->middleware('can:create_finance_payments')
            ->name('student-dng-data');
        Route::get('/auto-allocate', [PaymentController::class, 'showAutoAllocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('auto-allocate.show');
        Route::post('/auto-allocate/preview', [PaymentController::class, 'previewAutoAllocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('auto-allocate.preview');
        Route::post('/auto-allocate', [PaymentController::class, 'autoAllocate'])
            ->middleware('can:allocate_finance_payment')
            ->name('auto-allocate');
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
        });

        Route::prefix('webhook-events')->name('webhook-events.')->group(function () {
            Route::get('/', [DngWebhookEventController::class, 'index'])
                ->middleware('can:view_finance_dng_webhook_events')
                ->name('index');
            Route::get('/{dngWebhookEvent}', [DngWebhookEventController::class, 'show'])
                ->middleware('can:view_finance_dng_webhook_events')
                ->name('show');
        });
    });
});
