<?php

use App\Http\Controllers\StudentScholarshipController;
use App\Http\Controllers\StudentFinancialImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Student scholarship assignment routes
    Route::prefix('student-scholarships')->name('student-scholarships.')->group(function () {
        Route::get('/', [StudentScholarshipController::class, 'index'])
            ->name('index');

        Route::get('create', [StudentScholarshipController::class, 'create'])
            ->name('create');

        Route::post('/', [StudentScholarshipController::class, 'store'])
            ->name('store');

        Route::delete('{studentScholarship}', [StudentScholarshipController::class, 'destroy'])
            ->name('destroy');

        // Student financial import routes
        Route::prefix('imports')->name('imports.')->group(function () {
            Route::get('/', [StudentFinancialImportController::class, 'index'])
                ->name('index');

            Route::post('preview', [StudentFinancialImportController::class, 'preview'])
                ->name('preview');

            Route::post('import', [StudentFinancialImportController::class, 'import'])
                ->name('import');

            Route::get('template', [StudentFinancialImportController::class, 'downloadTemplate'])
                ->name('template');
        });

    });
});
