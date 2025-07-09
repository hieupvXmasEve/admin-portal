<?php

use App\Http\Controllers\Web\Users\UserController;
use App\Http\Controllers\Web\Users\UserExportController;
use App\Http\Controllers\Web\Users\UserImportController;
use Illuminate\Support\Facades\Route;
use App\Constants\UserRoutes;

Route::middleware('auth')->group(function () {

    // Import routes must come BEFORE resource routes to avoid conflicts

    // Show import form
    Route::get('users/import', [UserImportController::class, 'showImportForm'])
        ->middleware('can:import_user')
        ->name(UserRoutes::IMPORT_FORM);

    // File upload and preview
    Route::post('users/import/upload', [UserImportController::class, 'uploadFile'])
        ->middleware('can:import_user')
        ->name(UserRoutes::IMPORT_UPLOAD);

    // Preview import data
    Route::post('users/import/preview', [UserImportController::class, 'previewImport'])
        ->middleware('can:import_user')
        ->name(UserRoutes::IMPORT_PREVIEW);

    // Process import
    Route::post('users/import/process', [UserImportController::class, 'processImport'])
        ->middleware('can:import_user')
        ->name(UserRoutes::IMPORT_PROCESS);

    // Import history
    Route::get('users/import/history', [UserImportController::class, 'getImportHistory'])
        ->middleware('can:import_user')
        ->name(UserRoutes::IMPORT_HISTORY);

    // Debug endpoint
    Route::get('users/import/debug', [UserImportController::class, 'debug'])
        ->name(UserRoutes::IMPORT_DEBUG);

    // Template downloads
    Route::get('users/templates/{format}', [UserImportController::class, 'downloadTemplate'])
        ->middleware('can:import_user')
        ->name(UserRoutes::TEMPLATE_DOWNLOAD)
        ->where('format', 'simple|detailed|relationship');

    // Export data
    Route::get('users/export/excel', [UserExportController::class, 'exportExcel'])
        ->middleware('can:view_user')
        ->name(UserRoutes::EXPORT_EXCEL);

    Route::get('users/export/excel/filtered', [UserExportController::class, 'exportExcelWithCurrentFilters'])
        ->middleware('can:view_user')
        ->name(UserRoutes::EXPORT_EXCEL_FILTERED);

    // Resource routes come AFTER specific routes
    Route::get('users', [UserController::class, 'index'])
        ->middleware('can:view_user')
        ->name(UserRoutes::INDEX);
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('can:create_user')
        ->name(UserRoutes::CREATE);
    Route::post('users', [UserController::class, 'store'])
        ->middleware('can:create_user')
        ->name(UserRoutes::STORE);
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('can:view_user')
        ->name(UserRoutes::SHOW);
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('can:edit_user')
        ->name(UserRoutes::EDIT);
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('can:edit_user')
        ->name(UserRoutes::UPDATE);
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('can:delete_user')
        ->name(UserRoutes::DESTROY);
});
