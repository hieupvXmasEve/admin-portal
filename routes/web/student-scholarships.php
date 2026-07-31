<?php

use App\Http\Controllers\StudentFinancialImportController;
use App\Http\Controllers\StudentScholarshipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Student scholarship assignment routes
    Route::prefix('student-scholarships')->name('student-scholarships.')->group(function () {
        Route::get('/', [StudentScholarshipController::class, 'index'])
            ->middleware('can:view_scholarship')
            ->name('index');

        Route::get('create', [StudentScholarshipController::class, 'create'])
            ->middleware('can:assign_scholarship')
            ->name('create');

        Route::post('/', [StudentScholarshipController::class, 'store'])
            ->middleware('can:assign_scholarship')
            ->name('store');

        Route::delete('{studentScholarship}', [StudentScholarshipController::class, 'destroy'])
            ->middleware('can:remove_scholarship')
            ->name('destroy');

        // Student financial import routes: bulk mutation of student financial
        // data, so every route (including preview/template) requires the
        // import permission.
        Route::prefix('imports')->name('imports.')->middleware('can:import_student_financial')->group(function () {
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
