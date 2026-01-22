<?php

use App\Http\Controllers\FinanceChargeController;
use App\Modules\Finance\Http\Web\Admin\PaymentController;
use App\Modules\Finance\Http\Web\Admin\BillingOperationsController;
use App\Modules\Finance\Http\Web\Admin\BillingInvoiceController;
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
    Route::get('/invoices', [BillingInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/export', [BillingInvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/{invoice}', [BillingInvoiceController::class, 'show'])->name('invoices.show');
    // =====================
    // Finance Charges
    // =====================
    Route::get('/charges', [FinanceChargeController::class, 'index'])->name('charges.index');
    Route::get('/charges/create', [FinanceChargeController::class, 'create'])->name('charges.create');
    Route::post('/charges', [FinanceChargeController::class, 'store'])->name('charges.store');
    Route::get('/charges/{charge}', [FinanceChargeController::class, 'show'])->name('charges.show');
    Route::post('/charges/{charge}/void', [FinanceChargeController::class, 'void'])->name('charges.void');
    
    // Student Charges Summary
    Route::get('/students/{student}/charges', [FinanceChargeController::class, 'studentCharges'])->name('students.charges');

    // =====================
    // Payments
    // =====================
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
