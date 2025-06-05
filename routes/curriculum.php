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
    Route::prefix('curriculum-versions')->name('curriculum-versions.')->group(function () {
        Route::post('/', [CurriculumVersionController::class, 'apiStore'])->name('store');
        Route::delete('{curriculumVersion}', [CurriculumVersionController::class, 'apiDestroy'])->name('destroy');
        Route::get('specializations-by-program', [CurriculumVersionController::class, 'getSpecializationsByProgram'])
            ->name('specializations-by-program');
        Route::delete('bulk-delete', [CurriculumVersionController::class, 'bulkDelete'])->name('bulk-delete');
    });

    // Curriculum Units API routes
    Route::prefix('curriculum-units')->name('curriculum-units.')->group(function () {
        Route::get('by-curriculum-version', [CurriculumUnitController::class, 'getUnitsByCurriculumVersion'])
            ->name('by-curriculum-version');
        Route::delete('bulk-delete', [CurriculumUnitController::class, 'bulkDelete'])->name('bulk-delete');
    });
});
