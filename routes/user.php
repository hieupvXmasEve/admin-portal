<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

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
    });

    // Resource routes come AFTER specific routes
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'users',
        controller: UserController::class,
        module: 'users'
    );
});
