<?php

use App\Helpers\RoutePermissionHelper;
use App\Http\Controllers\CurriculumVersionController;
use App\Http\Controllers\CurriculumUnitController;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {

    // Curriculum Versions routes
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'curriculum-versions',
        controller: CurriculumVersionController::class,
        module: 'curriculum_versions'
    );

    // Global management routes for curriculum versions
    Route::prefix('curriculum-versions')->name('curriculum_version.')->group(function () {
        Route::get('export/excel/filtered', [CurriculumVersionController::class, 'exportFiltered'])
            ->middleware('can:export_curriculum_version')
            ->name('export.filtered');
    });

    // Curriculum Units routes
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'curriculum-units',
        controller: CurriculumUnitController::class,
        module: 'curriculum_units'
    );
});

// API routes for AJAX calls
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {

    // Curriculum Versions API routes
    Route::prefix('curriculum-versions')->name('curriculum_version.')->group(function () {
        Route::post('/', [CurriculumVersionController::class, 'apiStore'])->name('store');
        Route::delete('{curriculumVersion}', [CurriculumVersionController::class, 'apiDestroy'])->name('destroy');
        Route::get('specializations-by-program', [CurriculumVersionController::class, 'getSpecializationsByProgram'])
            ->name('specializations-by-program');
        Route::get('by-program-specialization', [CurriculumVersionController::class, 'getCurriculumVersionsByProgramSpecialization'])
            ->name('by-program-specialization');
        Route::delete('bulk-delete', [CurriculumVersionController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('bulk-operations', [CurriculumVersionController::class, 'bulkOperations'])
            ->name('bulk-operations');
    });

    // Curriculum Units API routes
    Route::prefix('curriculum-units')->name('curriculum-units.')->group(function () {
        Route::post('/', [CurriculumUnitController::class, 'apiStore'])->name('store');
        Route::put('{curriculumUnit}', [CurriculumUnitController::class, 'apiUpdate'])->name('update');
        Route::delete('{curriculumUnit}', [CurriculumUnitController::class, 'apiDestroy'])->name('destroy');
        Route::get('by-curriculum-version', [CurriculumUnitController::class, 'getUnitsByCurriculumVersion'])
            ->name('by-curriculum-version');
        Route::delete('bulk-delete', [CurriculumUnitController::class, 'bulkDelete'])->name('bulk-delete');
    });
});
