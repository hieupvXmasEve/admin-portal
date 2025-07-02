<?php

use App\Http\Controllers\Web\UnitController;
use App\Http\Controllers\Web\Units\UnitExportController;
use App\Http\Controllers\Web\Units\UnitImportController;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {
    // Import/Export routes
    Route::prefix('units')->group(function () {
        // Export routes
        Route::get('export/excel', [UnitController::class, 'exportExcel'])->name('units.export.excel');
        Route::get('export/excel/filtered', [UnitExportController::class, 'exportExcelWithCurrentFilters'])->name('units.export.excel.filtered');

        // Import routes
        Route::get('import', [UnitImportController::class, 'showImportForm'])->name('units.import');
        Route::post('import/upload', [UnitImportController::class, 'uploadFile'])->name('units.import.upload');
        Route::post('import/preview', [UnitImportController::class, 'previewImport'])->name('units.import.preview');
        Route::post('import/process', [UnitImportController::class, 'processImport'])->name('units.import.process');
        Route::get('import/template/{format}', [UnitImportController::class, 'downloadTemplate'])->name('units.import.template');
        Route::get('import/history', [UnitImportController::class, 'getImportHistory'])->name('units.import.history');
    });

    Route::get('units', [UnitController::class, 'index'])
        ->middleware('can:view_unit')
        ->name('unit.index');
    Route::get('units/create', [UnitController::class, 'create'])
        ->middleware('can:create_unit')
        ->name('unit.create');
    Route::post('units', [UnitController::class, 'store'])
        ->middleware('can:create_unit')
        ->name('unit.store');
    Route::get('units/{unit}', [UnitController::class, 'show'])
        ->middleware('can:view_unit')
        ->name('unit.show');
    Route::get('units/{unit}/edit', [UnitController::class, 'edit'])
        ->middleware('can:edit_unit')
        ->name('unit.edit');
    Route::put('units/{unit}', [UnitController::class, 'update'])
        ->middleware('can:edit_unit')
        ->name('unit.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])
        ->middleware('can:delete_unit')
        ->name('unit.destroy');
});

// API routes for AJAX calls
Route::middleware(['auth'])->prefix('api/units')->name('api.units.')->group(function () {
    Route::get('search', [UnitController::class, 'search'])->name('search');
    Route::post('validate-code', [UnitController::class, 'validateCode'])->name('validate-code');
    Route::post('validate-prerequisite-expression', [UnitController::class, 'validatePrerequisiteExpression'])
        ->name('validate-prerequisite-expression');
    Route::delete('bulk-delete', [UnitController::class, 'bulkDelete'])->name('bulk-delete');
});
