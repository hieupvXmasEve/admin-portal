<?php

use App\Http\Controllers\Web\Users\UserController;
use App\Http\Controllers\Web\Users\UserExportController;
use App\Http\Controllers\Web\Users\UserImportController;
use Illuminate\Support\Facades\Route;
use App\Helpers\RoutePermissionHelper;

Route::middleware('auth')->group(function () {

    // Import routes must come BEFORE resource routes to avoid conflicts
    Route::prefix('users')->group(function () {

        // Import form and processing
        Route::prefix('import')->name('users.import.')->group(function () {

            // Show import form
            Route::get('/', [UserImportController::class, 'showImportForm'])
                ->middleware('can:import_user')
                ->name('form');

            // File upload and preview
            Route::post('/upload', [UserImportController::class, 'uploadFile'])
                ->middleware('can:import_user')
                ->name('upload');

            // Preview import data
            Route::post('/preview', [UserImportController::class, 'previewImport'])
                ->middleware('can:import_user')
                ->name('preview');

            // Process import
            Route::post('/process', [UserImportController::class, 'processImport'])
                ->middleware('can:import_user')
                ->name('process');

            // Import history
            Route::get('/history', [UserImportController::class, 'getImportHistory'])
                ->middleware('can:import_user')
                ->name('history');

            // Debug endpoint
            Route::get('/debug', [UserImportController::class, 'debug'])
                ->name('debug');
        });

        // Template downloads
        Route::prefix('templates')->name('users.templates.')->group(function () {

            Route::get('/{format}', [UserImportController::class, 'downloadTemplate'])
                ->middleware('can:import_user')
                ->name('download')
                ->where('format', 'simple|detailed|relationship');
        });

        // Export data
        Route::get('/export/excel', [UserExportController::class, 'exportExcel'])
            ->middleware('can:view_user')
            ->name('user.export.excel');

        Route::get('/export/excel/filtered', [UserExportController::class, 'exportExcelWithCurrentFilters'])
            ->middleware('can:view_user')
            ->name('user.export.excel.filtered');
    });

    // Resource routes come AFTER specific routes
    Route::get('users', [UserController::class, 'index'])
        ->middleware('can:view_user')
        ->name('user.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('can:create_user')
        ->name('user.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('can:create_user')
        ->name('user.store');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('can:view_user')
        ->name('user.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('can:edit_user')
        ->name('user.edit');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('can:edit_user')
        ->name('user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('can:delete_user')
        ->name('user.destroy');
});
