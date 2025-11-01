<?php

use App\Constants\CurriculumRoutes;
use App\Http\Controllers\Web\CurriculumModuleController;
use App\Http\Controllers\Web\CurriculumUnitController;
use App\Http\Controllers\Web\CurriculumVersionController;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {

    // Curriculum Versions routes
    Route::prefix('curriculum-versions')->group(function () {
        Route::get('/', [CurriculumVersionController::class, 'index'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_INDEX);
        Route::get('/create', [CurriculumVersionController::class, 'create'])
            ->middleware('can:create_curriculum_version')
            ->name(CurriculumRoutes::VERSION_CREATE);
        Route::post('', [CurriculumVersionController::class, 'store'])
            ->middleware('can:create_curriculum_version')
            ->name(CurriculumRoutes::VERSION_STORE);
        // Route::get('/{curriculum_version}', [CurriculumVersionController::class, 'show'])
        //     ->middleware('can:view_curriculum_version')
        //     ->name(CurriculumRoutes::VERSION_SHOW);

        // Tab-based detail routes
        Route::get('/{curriculum_version}/overview', [CurriculumVersionController::class, 'summaryOverview'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW);
        Route::get('/{curriculum_version}/units', [CurriculumVersionController::class, 'summaryUnits'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_SUMMARY_UNITS);
        Route::get('/{curriculum_version}/modules', [CurriculumVersionController::class, 'summaryModules'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_SUMMARY_MODULES);
        Route::get('/{curriculum_version}/students', [CurriculumVersionController::class, 'summaryStudents'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_SUMMARY_STUDENTS);
        Route::get('/{curriculum_version}/deployments', [CurriculumVersionController::class, 'summaryDeployments'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_SUMMARY_DEPLOYMENTS);
        Route::get('/{curriculum_version}/edit', [CurriculumVersionController::class, 'edit'])
            ->middleware('can:edit_curriculum_version')
            ->name(CurriculumRoutes::VERSION_EDIT);
        Route::put('/{curriculum_version}', [CurriculumVersionController::class, 'update'])
            ->middleware('can:edit_curriculum_version')
            ->name(CurriculumRoutes::VERSION_UPDATE);
        Route::delete('/{curriculum_version}', [CurriculumVersionController::class, 'destroy'])
            ->middleware('can:delete_curriculum_version')
            ->name(CurriculumRoutes::VERSION_DESTROY);
        Route::post('/{curriculum_version}/duplicate', [CurriculumVersionController::class, 'duplicate'])
            ->middleware('can:create_curriculum_version')
            ->name(CurriculumRoutes::VERSION_DUPLICATE);

        // Curriculum Module Management
        Route::post('/{curriculum_version}/modules/attach', [CurriculumModuleController::class, 'attach'])
            ->middleware('can:edit_curriculum_version')
            ->name('curriculum-versions.modules.attach');
        Route::post('/{curriculum_version}/modules/detach', [CurriculumModuleController::class, 'detach'])
            ->middleware('can:edit_curriculum_version')
            ->name('curriculum-versions.modules.detach');
        Route::put('/curriculum-modules/{curriculum_module}', [CurriculumModuleController::class, 'update'])
            ->middleware('can:edit_curriculum_version')
            ->name('curriculum-modules.update');
    });

    // Global management routes for curriculum versions
    Route::get('curriculum-versions/export/excel/filtered', [CurriculumVersionController::class, 'exportFiltered'])
        ->middleware('can:export_curriculum_version')
        ->name(CurriculumRoutes::VERSION_EXPORT_FILTERED);

    // Curriculum Units routes
    Route::get('curriculum-units', [CurriculumUnitController::class, 'index'])
        ->middleware('can:view_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_INDEX);
    Route::get('curriculum-units/create', [CurriculumUnitController::class, 'create'])
        ->middleware('can:create_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_CREATE);
    Route::post('curriculum-units', [CurriculumUnitController::class, 'store'])
        ->middleware('can:create_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_STORE);
    Route::get('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'show'])
        ->middleware('can:view_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_SHOW);
    Route::get('curriculum-units/{curriculum_unit}/edit', [CurriculumUnitController::class, 'edit'])
        ->middleware('can:edit_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_EDIT);
    Route::put('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'update'])
        ->middleware('can:edit_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_UPDATE);
    Route::delete('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'destroy'])
        ->middleware('can:delete_curriculum_unit')
        ->name(CurriculumRoutes::UNIT_DESTROY);
});

// API routes for AJAX calls
Route::middleware(['auth'])->group(function () {

    // Curriculum Versions API routes
    Route::post('api/curriculum-versions', [CurriculumVersionController::class, 'apiStore'])
        ->name(CurriculumRoutes::API_VERSION_STORE);
    Route::delete('api/curriculum-versions/{curriculumVersion}', [CurriculumVersionController::class, 'apiDestroy'])
        ->name(CurriculumRoutes::API_VERSION_DESTROY);
    Route::get('api/curriculum-versions/specializations-by-program', [CurriculumVersionController::class, 'getSpecializationsByProgram'])
        ->name(CurriculumRoutes::API_VERSION_SPECIALIZATIONS_BY_PROGRAM);
    Route::get('api/curriculum-versions/by-program-specialization', [CurriculumVersionController::class, 'getCurriculumVersionsByProgramSpecialization'])
        ->name(CurriculumRoutes::API_VERSION_BY_PROGRAM_SPECIALIZATION);
    Route::delete('api/curriculum-versions/bulk-delete', [CurriculumVersionController::class, 'bulkDelete'])
        ->name(CurriculumRoutes::API_VERSION_BULK_DELETE);
    Route::post('api/curriculum-versions/bulk-operations', [CurriculumVersionController::class, 'bulkOperations'])
        ->name(CurriculumRoutes::API_VERSION_BULK_OPERATIONS);

    // Curriculum Units API routes
    Route::post('api/curriculum-units', [CurriculumUnitController::class, 'apiStore'])
        ->name(CurriculumRoutes::API_UNIT_STORE);
    Route::put('api/curriculum-units/{curriculumUnit}', [CurriculumUnitController::class, 'apiUpdate'])
        ->name(CurriculumRoutes::API_UNIT_UPDATE);
    Route::delete('api/curriculum-units/{curriculumUnit}', [CurriculumUnitController::class, 'apiDestroy'])
        ->name(CurriculumRoutes::API_UNIT_DESTROY);
    Route::get('api/curriculum-units/by-curriculum-version', [CurriculumUnitController::class, 'getUnitsByCurriculumVersion'])
        ->name(CurriculumRoutes::API_UNIT_BY_CURRICULUM_VERSION);
    Route::delete('api/curriculum-units/bulk-delete', [CurriculumUnitController::class, 'bulkDelete'])
        ->name(CurriculumRoutes::API_UNIT_BULK_DELETE);
});
