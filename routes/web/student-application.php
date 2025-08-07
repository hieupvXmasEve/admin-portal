<?php

use App\Http\Controllers\Web\StudentApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('student-applications')->name('student-applications.')->group(function () {
    Route::get('/', [StudentApplicationController::class, 'index'])
        ->middleware('can:view_student_application')
        ->name('index');
    Route::get('/create', [StudentApplicationController::class, 'create'])
        ->middleware('can:create_student_application')
        ->name('create');
    Route::post('/', [StudentApplicationController::class, 'store'])
        ->middleware('can:create_student_application')
        ->name('store');
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

    // Status management
    Route::patch('/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus'])
        ->middleware('can:edit_student_application')
        ->name('update-status');

    // Conversion routes
    Route::post('/{studentApplication}/convert', [StudentApplicationController::class, 'convert'])
        ->middleware('can:edit_student_application')
        ->name('convert');
    Route::post('/batch-convert', [StudentApplicationController::class, 'batchConvert'])
        ->middleware('can:edit_student_application')
        ->name('batch-convert');

    // API routes for forms
    Route::get('/api/conversion-options', [StudentApplicationController::class, 'getConversionOptions'])
        ->middleware('can:view_student_application')
        ->name('conversion-options');
});
