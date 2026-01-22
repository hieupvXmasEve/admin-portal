<?php

use App\Http\Controllers\FinanceChargeController;
use App\Modules\Finance\Http\Web\Admin\BillingInvoiceController;
use App\Modules\Finance\Http\Web\Admin\BillingOperationsController;
use App\Modules\Finance\Http\Web\Admin\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->prefix('finance')->name('finance.')->group(function () {
    // ... other finance routes ...

    // Operations
    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/dashboard', [BillingOperationsController::class, 'dashboard'])->name('dashboard');

        Route::get('/generate-charges', [BillingOperationsController::class, 'showGenerateCharges'])->name('generate-charges');

        Route::get('/exceptions', [BillingOperationsController::class, 'exceptions'])->name('exceptions');

        Route::get('/due-calendar', [BillingOperationsController::class, 'dueCalendar'])->name('due-calendar');

        Route::get('/export-due-list', [BillingOperationsController::class, 'exportDueList'])->name('export-due-list');
    });

    // Invoices

    // Invoices
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [BillingInvoiceController::class, 'index'])->name('index');
        Route::get('/export', [BillingInvoiceController::class, 'export'])->name('export');
        Route::get('/{invoice}', [BillingInvoiceController::class, 'show'])->name('show');
    });
    // =====================
    // Finance Charges
    // =====================
    Route::prefix('charges')->name('charges.')->group(function () {
        Route::get('/', [FinanceChargeController::class, 'index'])->name('index');
        Route::get('/create', [FinanceChargeController::class, 'create'])->name('create');
        Route::post('/', [FinanceChargeController::class, 'store'])->name('store');
        Route::get('/{charge}', [FinanceChargeController::class, 'show'])->name('show');
        Route::post('/{charge}/void', [FinanceChargeController::class, 'void'])->name('void');
    });

    // Student Charges Summary
    Route::get('/students/{student}/charges', [FinanceChargeController::class, 'studentCharges'])->name('students.charges');
    // =====================
    // Payments
    // =====================
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/import', [PaymentController::class, 'import'])->name('import');
        Route::get('/import/template', [PaymentController::class, 'downloadTemplate'])->name('import.template');
        Route::post('/import/preview', [PaymentController::class, 'previewImport'])->name('import.preview');
        Route::post('/import/store', [PaymentController::class, 'storeImport'])->name('import.store');
        Route::post('/auto-allocate', [PaymentController::class, 'autoAllocate'])->name('auto-allocate');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/allocate', [PaymentController::class, 'allocate'])->name('allocate');
    });
});
