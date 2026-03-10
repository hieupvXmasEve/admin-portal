<?php

use App\Modules\Finance\Http\Web\Admin\BillingInvoiceController;
use App\Modules\Finance\Http\Web\Admin\BillingOperationsController;
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
    });

    // =====================
    // Payments
    // =====================
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('can:view_finance_payments')
            ->name('index');
        Route::get('/import', [PaymentController::class, 'import'])
            ->middleware('can:import_finance_payments')
            ->name('import');
        Route::get('/import/template', [PaymentController::class, 'downloadTemplate'])
            ->middleware('can:import_finance_payments')
            ->name('import.template');
        Route::post('/import/preview', [PaymentController::class, 'previewImport'])
            ->middleware('can:import_finance_payments')
            ->name('import.preview');
        Route::post('/import/store', [PaymentController::class, 'storeImport'])
            ->middleware('can:import_finance_payments')
            ->name('import.store');
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
});
