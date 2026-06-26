<?php

use App\Http\Controllers\Web\StudentApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('student-applications')->name('student-applications.')->group(function () {
    // List route
    Route::get('/', [StudentApplicationController::class, 'index'])
        ->middleware('can:view_student_application')
        ->name('index');

    // Static routes (must come before parameterized routes)
    Route::get('/create', [StudentApplicationController::class, 'create'])
        ->middleware('can:create_student_application')
        ->name('create');

    // Export route (must come before {studentApplication} route)
    Route::get('/export', [StudentApplicationController::class, 'export'])
        ->middleware('can:view_student_application')
        ->name('export');

    // API routes for forms
    Route::get('/api/conversion-options', [StudentApplicationController::class, 'getConversionOptions'])
        ->middleware('can:view_student_application')
        ->name('conversion-options');

    // Batch operations
    Route::post('/batch-convert', [StudentApplicationController::class, 'batchConvert'])
        ->middleware('can:edit_student_application')
        ->name('batch-convert');

    // Store route
    Route::post('/', [StudentApplicationController::class, 'store'])
        ->middleware('can:create_student_application')
        ->name('store');

    // Parameterized routes (must come after static routes)
    Route::get('/{studentApplication}', [StudentApplicationController::class, 'show'])
        ->middleware('can:view_student_application')
        ->name('show');
    Route::get('/{studentApplication}/edit', [StudentApplicationController::class, 'edit'])
        ->middleware('can:edit_student_application')
        ->name('edit');
    Route::put('/{studentApplication}', [StudentApplicationController::class, 'update'])
        ->middleware('can:edit_student_application')
        ->name('update');
    Route::delete('/{studentApplication}', [StudentApplicationController::class, 'destroy'])
        ->middleware('can:delete_student_application')
        ->name('destroy');
    Route::patch('/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus'])
        ->middleware('can:edit_student_application')
        ->name('update-status');
    Route::post('/{studentApplication}/convert', [StudentApplicationController::class, 'convert'])
        ->middleware('can:edit_student_application')
        ->name('convert');
});
