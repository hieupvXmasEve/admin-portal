<?php

use App\Http\Controllers\Web\UnitController;
use App\Http\Controllers\Web\Units\UnitExportController;
use App\Http\Controllers\Web\Units\UnitImportController;
use App\Constants\UnitRoutes;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {
    // Import/Export routes
    // Export routes
    Route::get('units/export/excel', [UnitController::class, 'exportExcel'])
        ->name(UnitRoutes::EXPORT_EXCEL);
    Route::get('units/export/excel/filtered', [UnitExportController::class, 'exportExcelWithCurrentFilters'])
        ->name(UnitRoutes::EXPORT_EXCEL_FILTERED);

    // Import routes
    Route::get('units/import', [UnitImportController::class, 'showImportForm'])
        ->name(UnitRoutes::IMPORT);
    Route::post('units/import/upload', [UnitImportController::class, 'uploadFile'])
        ->name(UnitRoutes::IMPORT_UPLOAD);
    Route::post('units/import/preview', [UnitImportController::class, 'previewImport'])
        ->name(UnitRoutes::IMPORT_PREVIEW);
    Route::post('units/import/process', [UnitImportController::class, 'processImport'])
        ->name(UnitRoutes::IMPORT_PROCESS);
    Route::get('units/import/template/{format}', [UnitImportController::class, 'downloadTemplate'])
        ->name(UnitRoutes::IMPORT_TEMPLATE);
    Route::get('units/import/history', [UnitImportController::class, 'getImportHistory'])
        ->name(UnitRoutes::IMPORT_HISTORY);

    Route::get('units', [UnitController::class, 'index'])
        ->middleware('can:view_unit')
        ->name(UnitRoutes::INDEX);
    Route::get('units/create', [UnitController::class, 'create'])
        ->middleware('can:create_unit')
        ->name(UnitRoutes::CREATE);
    Route::post('units', [UnitController::class, 'store'])
        ->middleware('can:create_unit')
        ->name(UnitRoutes::STORE);
    Route::get('units/{unit}', [UnitController::class, 'show'])
        ->middleware('can:view_unit')
        ->name(UnitRoutes::SHOW);
    Route::get('units/{unit}/edit', [UnitController::class, 'edit'])
        ->middleware('can:edit_unit')
        ->name(UnitRoutes::EDIT);
    Route::put('units/{unit}', [UnitController::class, 'update'])
        ->middleware('can:edit_unit')
        ->name(UnitRoutes::UPDATE);
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])
        ->middleware('can:delete_unit')
        ->name(UnitRoutes::DESTROY);
});

// API routes for AJAX calls
Route::middleware(['auth'])->group(function () {
    Route::get('api/units/search', [UnitController::class, 'search'])
        ->name(UnitRoutes::API_SEARCH);
    Route::post('api/units/validate-code', [UnitController::class, 'validateCode'])
        ->name(UnitRoutes::API_VALIDATE_CODE);
    Route::post('api/units/validate-prerequisite-expression', [UnitController::class, 'validatePrerequisiteExpression'])
        ->name(UnitRoutes::API_VALIDATE_PREREQUISITE_EXPRESSION);
    Route::delete('api/units/bulk-delete', [UnitController::class, 'bulkDelete'])
        ->name(UnitRoutes::API_BULK_DELETE);
});
