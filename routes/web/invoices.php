<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Invoice routes
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/generate', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/items', [InvoiceController::class, 'addItem'])->name('invoices.add-item');
    Route::post('/invoices/{invoice}/discounts', [InvoiceController::class, 'applyDiscount'])->name('invoices.apply-discount');
});
