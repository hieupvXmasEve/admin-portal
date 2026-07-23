<?php

use App\Constants\CurriculumRoutes;
use App\Modules\Academic\Catalog\Http\Web\CurriculumVersionController;
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
        Route::get('/{curriculum_version}/roadmap', [CurriculumVersionController::class, 'summaryRoadmap'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_SUMMARY_ROADMAP);
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

    });

    // Global management routes for curriculum versions
    Route::get('curriculum-versions/export/excel/filtered', [CurriculumVersionController::class, 'exportFiltered'])
        ->middleware('can:export_curriculum_version')
        ->name(CurriculumRoutes::VERSION_EXPORT_FILTERED);

});

// API routes for AJAX calls
Route::middleware(['auth'])->group(function () {

    // Curriculum Versions API routes
    Route::post('api/curriculum-versions', [CurriculumVersionController::class, 'apiStore'])
        ->name(CurriculumRoutes::API_VERSION_STORE);
    Route::get('api/curriculum-versions/specializations-by-program', [CurriculumVersionController::class, 'getSpecializationsByProgram'])
        ->name(CurriculumRoutes::API_VERSION_SPECIALIZATIONS_BY_PROGRAM);
    Route::get('api/curriculum-versions/by-program-specialization', [CurriculumVersionController::class, 'getCurriculumVersionsByProgramSpecialization'])
        ->name(CurriculumRoutes::API_VERSION_BY_PROGRAM_SPECIALIZATION);
    Route::delete('api/curriculum-versions/bulk-delete', [CurriculumVersionController::class, 'bulkDelete'])
        ->name(CurriculumRoutes::API_VERSION_BULK_DELETE);
    Route::post('api/curriculum-versions/bulk-operations', [CurriculumVersionController::class, 'bulkOperations'])
        ->name(CurriculumRoutes::API_VERSION_BULK_OPERATIONS);
    Route::delete('api/curriculum-versions/{curriculumVersion}', [CurriculumVersionController::class, 'apiDestroy'])
        ->whereNumber('curriculumVersion')
        ->name(CurriculumRoutes::API_VERSION_DESTROY);

});
