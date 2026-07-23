<?php

declare(strict_types=1);

use App\Constants\SpecializationRoutes;
use App\Http\Controllers\Web\SpecializationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Specialization resource routes
    Route::get('specializations', [SpecializationController::class, 'index'])
        ->middleware('can:view_specialization')
        ->name(SpecializationRoutes::INDEX);

    Route::get('specializations/create', [SpecializationController::class, 'create'])
        ->middleware('can:create_specialization')
        ->name(SpecializationRoutes::CREATE);

    Route::get('specializations/{specialization}/edit', [SpecializationController::class, 'edit'])
        ->middleware('can:edit_specialization')
        ->name(SpecializationRoutes::EDIT);

    Route::post('specializations', [SpecializationController::class, 'store'])
        ->middleware('can:create_specialization')
        ->name(SpecializationRoutes::STORE);

    Route::get('specializations/{specialization}', [SpecializationController::class, 'show'])
        ->middleware('can:view_specialization')
        ->name(SpecializationRoutes::SHOW);

    Route::put('specializations/{specialization}', [SpecializationController::class, 'update'])
        ->middleware('can:edit_specialization')
        ->name(SpecializationRoutes::UPDATE);

    Route::delete('specializations/{specialization}', [SpecializationController::class, 'destroy'])
        ->middleware('can:delete_specialization')
        ->name(SpecializationRoutes::DESTROY);

    // API routes for operations
    Route::delete('api/specializations/{specialization}', [SpecializationController::class, 'apiDestroy'])
        ->middleware('can:delete_specialization')
        ->name(SpecializationRoutes::API_DESTROY);

    Route::delete('api/specializations/bulk-delete', [SpecializationController::class, 'bulkDelete'])
        ->name(SpecializationRoutes::API_BULK_DELETE);

    // API routes for curriculum version operations within specializations
    Route::delete('api/curriculum-versions/{curriculumVersion}', [SpecializationController::class, 'apiDeleteCurriculumVersion'])
        ->middleware('can:delete_curriculum_version')
        ->whereNumber('curriculumVersion')
        ->name(SpecializationRoutes::API_CURRICULUM_VERSION_DESTROY);

    Route::put('api/curriculum-versions/{curriculumVersion}', [SpecializationController::class, 'apiUpdateCurriculumVersion'])
        ->middleware('can:edit_curriculum_version')
        ->whereNumber('curriculumVersion')
        ->name(SpecializationRoutes::API_CURRICULUM_VERSION_UPDATE);
});
