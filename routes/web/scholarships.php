<?php

use App\Http\Controllers\ScholarshipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Scholarship management routes
    Route::prefix('scholarships')->name('scholarships.')->group(function () {
        Route::get('/', [ScholarshipController::class, 'index'])
            ->middleware('can:view_scholarship')
            ->name('index');

        Route::get('create', [ScholarshipController::class, 'create'])
            ->middleware('can:assign_scholarship')
            ->name('create');

        Route::post('/', [ScholarshipController::class, 'store'])
            ->middleware('can:assign_scholarship')
            ->name('store');

        Route::get('{scholarship}', [ScholarshipController::class, 'show'])
            ->middleware('can:view_scholarship')
            ->name('show');

        Route::get('{scholarship}/edit', [ScholarshipController::class, 'edit'])
            ->middleware('can:assign_scholarship')
            ->name('edit');

        Route::put('{scholarship}', [ScholarshipController::class, 'update'])
            ->middleware('can:assign_scholarship')
            ->name('update');

        Route::delete('{scholarship}', [ScholarshipController::class, 'destroy'])
            ->middleware('can:remove_scholarship')
            ->name('destroy');
    });
});
