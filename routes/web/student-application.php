<?php

use App\Http\Controllers\Web\StudentApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('student-applications')->name('student-applications.')->group(function () {
    Route::get('/', [StudentApplicationController::class, 'index'])->name('index');
    Route::get('/create', [StudentApplicationController::class, 'create'])->name('create');
    Route::post('/', [StudentApplicationController::class, 'store'])->name('store');
    Route::get('/{studentApplication}', [StudentApplicationController::class, 'show'])->name('show');
    Route::get('/{studentApplication}/edit', [StudentApplicationController::class, 'edit'])->name('edit');
    Route::put('/{studentApplication}', [StudentApplicationController::class, 'update'])->name('update');
    Route::delete('/{studentApplication}', [StudentApplicationController::class, 'destroy'])->name('destroy');
    
    // Status management
    Route::patch('/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus'])->name('update-status');
    
    // Conversion routes
    Route::post('/{studentApplication}/convert', [StudentApplicationController::class, 'convert'])->name('convert');
    Route::post('/batch-convert', [StudentApplicationController::class, 'batchConvert'])->name('batch-convert');
    
    // API routes for forms
    Route::get('/api/conversion-options', [StudentApplicationController::class, 'getConversionOptions'])->name('conversion-options');
});
