<?php

use App\Http\Controllers\ScholarshipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Scholarship management routes
    Route::prefix('scholarships')->name('scholarships.')->group(function () {
        Route::get('/', [ScholarshipController::class, 'index'])
            ->name('index');

        Route::get('create', [ScholarshipController::class, 'create'])
            ->name('create');

        Route::post('/', [ScholarshipController::class, 'store'])
            ->name('store');

        Route::get('{scholarship}', [ScholarshipController::class, 'show'])
            ->name('show');

        Route::get('{scholarship}/edit', [ScholarshipController::class, 'edit'])
            ->name('edit');

        Route::put('{scholarship}', [ScholarshipController::class, 'update'])
            ->name('update');

        Route::delete('{scholarship}', [ScholarshipController::class, 'destroy'])
            ->name('destroy');
    });
});
