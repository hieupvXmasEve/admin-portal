<?php

use App\Http\Controllers\Web\CurriculumVersionController;
use App\Http\Controllers\Web\CurriculumUnitController;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {

    // Curriculum Versions routes
    Route::get('curriculum-versions', [CurriculumVersionController::class, 'index'])
        ->middleware('can:view_curriculum_version')
        ->name('curriculum_version.index');
    Route::get('curriculum-versions/create', [CurriculumVersionController::class, 'create'])
        ->middleware('can:create_curriculum_version')
        ->name('curriculum_version.create');
    Route::post('curriculum-versions', [CurriculumVersionController::class, 'store'])
        ->middleware('can:create_curriculum_version')
        ->name('curriculum_version.store');
    Route::get('curriculum-versions/{curriculum_version}', [CurriculumVersionController::class, 'show'])
        ->middleware('can:view_curriculum_version')
        ->name('curriculum_version.show');
    Route::get('curriculum-versions/{curriculum_version}/edit', [CurriculumVersionController::class, 'edit'])
        ->middleware('can:edit_curriculum_version')
        ->name('curriculum_version.edit');
    Route::put('curriculum-versions/{curriculum_version}', [CurriculumVersionController::class, 'update'])
        ->middleware('can:edit_curriculum_version')
        ->name('curriculum_version.update');
    Route::delete('curriculum-versions/{curriculum_version}', [CurriculumVersionController::class, 'destroy'])
        ->middleware('can:delete_curriculum_version')
        ->name('curriculum_version.destroy');

    // Global management routes for curriculum versions
    Route::prefix('curriculum-versions')->name('curriculum_version.')->group(function () {
        Route::get('export/excel/filtered', [CurriculumVersionController::class, 'exportFiltered'])
            ->middleware('can:export_curriculum_version')
            ->name('export.filtered');
    });

    // Curriculum Units routes
    Route::get('curriculum-units', [CurriculumUnitController::class, 'index'])
        ->middleware('can:view_curriculum_unit')
        ->name('curriculum_unit.index');
    Route::get('curriculum-units/create', [CurriculumUnitController::class, 'create'])
        ->middleware('can:create_curriculum_unit')
        ->name('curriculum_unit.create');
    Route::post('curriculum-units', [CurriculumUnitController::class, 'store'])
        ->middleware('can:create_curriculum_unit')
        ->name('curriculum_unit.store');
    Route::get('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'show'])
        ->middleware('can:view_curriculum_unit')
        ->name('curriculum_unit.show');
    Route::get('curriculum-units/{curriculum_unit}/edit', [CurriculumUnitController::class, 'edit'])
        ->middleware('can:edit_curriculum_unit')
        ->name('curriculum_unit.edit');
    Route::put('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'update'])
        ->middleware('can:edit_curriculum_unit')
        ->name('curriculum_unit.update');
    Route::delete('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'destroy'])
        ->middleware('can:delete_curriculum_unit')
        ->name('curriculum_unit.destroy');
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
