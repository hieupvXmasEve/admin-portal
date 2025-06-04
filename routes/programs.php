<?php

use App\Http\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {

    // Programs routes with consistent naming
    Route::prefix('programs')->name('programs.')->group(function () {
        Route::get('/', [ProgramController::class, 'index'])
            ->middleware('can:view_program')
            ->name('index');

        Route::post('/', [ProgramController::class, 'store'])
            ->middleware('can:create_program')
            ->name('store');

        Route::get('/{program}', [ProgramController::class, 'show'])
            ->middleware('can:view_program')
            ->name('show');

        Route::put('/{program}', [ProgramController::class, 'update'])
            ->middleware('can:edit_program')
            ->name('update');

        Route::delete('/{program}', [ProgramController::class, 'destroy'])
            ->middleware('can:delete_program')
            ->name('destroy');
    });
});

// API routes for AJAX calls
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {

    // Programs API routes
    Route::prefix('programs')->name('programs.')->group(function () {
        Route::get('search', [ProgramController::class, 'search'])->name('search');
        Route::delete('bulk-delete', [ProgramController::class, 'bulkDelete'])->name('bulk-delete');
    });
});
