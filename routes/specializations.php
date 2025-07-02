<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SpecializationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Specialization resource routes
    Route::prefix('specializations')->name('specializations.')->group(function () {
        Route::get('/', [SpecializationController::class, 'index'])
            ->middleware('can:view_specialization')
            ->name('index');

        Route::get('/create', [SpecializationController::class, 'create'])
            ->middleware('can:create_specialization')
            ->name('create');

        Route::get('/{specialization}/edit', [SpecializationController::class, 'edit'])
            ->middleware('can:edit_specialization')
            ->name('edit');

        Route::post('/', [SpecializationController::class, 'store'])
            ->middleware('can:create_specialization')
            ->name('store');

        Route::get('/{specialization}', [SpecializationController::class, 'show'])
            ->middleware('can:view_specialization')
            ->name('show');

        Route::put('/{specialization}', [SpecializationController::class, 'update'])
            ->middleware('can:edit_specialization')
            ->name('update');

        Route::delete('/{specialization}', [SpecializationController::class, 'destroy'])
            ->middleware('can:delete_specialization')
            ->name('destroy');
    });



    // API routes for operations
    Route::delete('api/specializations/{specialization}', [SpecializationController::class, 'apiDestroy'])
        ->middleware('can:delete_specialization')
        ->name('api.specializations.destroy');

    Route::delete('api/specializations/bulk-delete', [SpecializationController::class, 'bulkDelete'])
        ->name('api.specializations.bulk-delete');

    // API routes for curriculum version operations within specializations
    Route::delete('api/curriculum-versions/{curriculumVersion}', [SpecializationController::class, 'apiDeleteCurriculumVersion'])
        ->middleware('can:delete_curriculum_version')
        ->name('api.curriculum_version.destroy');

    Route::put('api/curriculum-versions/{curriculumVersion}', [SpecializationController::class, 'apiUpdateCurriculumVersion'])
        ->middleware('can:edit_curriculum_version')
        ->name('api.curriculum_version.update');
});
