<?php

declare(strict_types=1);

use App\Constants\CurriculumRoutes;
use App\Http\Controllers\Api\ElectiveController;
use App\Modules\Academic\Catalog\Http\Web\CurriculumVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::prefix('curriculum-versions')->group(function (): void {
        Route::get('/', [CurriculumVersionController::class, 'index'])
            ->middleware('can:view_curriculum_version')
            ->name(CurriculumRoutes::VERSION_INDEX);
        Route::get('/create', [CurriculumVersionController::class, 'create'])
            ->middleware('can:create_curriculum_version')
            ->name(CurriculumRoutes::VERSION_CREATE);
        Route::post('', [CurriculumVersionController::class, 'store'])
            ->middleware('can:create_curriculum_version')
            ->name(CurriculumRoutes::VERSION_STORE);

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
        Route::get('/export/excel/filtered', [CurriculumVersionController::class, 'exportFiltered'])
            ->middleware('can:export_curriculum_version')
            ->name(CurriculumRoutes::VERSION_EXPORT_FILTERED);
        Route::get('/{curriculum_version}', [CurriculumVersionController::class, 'show'])
            ->middleware(['verified', 'can:view_curriculum_version'])
            ->name('curriculum-versions.show');
    });
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/curriculum-versions/{curriculumVersion}/electives', [CurriculumVersionController::class, 'electiveManagement'])
        ->middleware('can:view_curriculum_version')
        ->name('curriculum_version.electives');

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::get('/curriculum-versions/{curriculumVersion}/available-electives', [ElectiveController::class, 'getAvailableElectives'])
            ->middleware('can:view_curriculum_version')
            ->name('curriculum_version.available-electives');
        Route::get('/curriculum-versions/{curriculumVersion}/elective-slots', [ElectiveController::class, 'getElectiveSlots'])
            ->middleware('can:view_curriculum_version')
            ->name('curriculum_version.elective-slots');
        Route::put('/curriculum-units/{curriculumUnit}/update-elective', [ElectiveController::class, 'updateElectiveSlot'])
            ->middleware('can:edit_curriculum_version')
            ->name('curriculum-units.update-elective');
        Route::get('/units/{unit}/details', [ElectiveController::class, 'getUnitDetails'])
            ->middleware('can:view_unit')
            ->name('units.details');
        Route::get('/curriculum-units/{curriculumUnit}/recommendations', [ElectiveController::class, 'getElectiveRecommendations'])
            ->middleware('can:view_curriculum_version')
            ->name('curriculum-units.recommendations');
    });
});

Route::middleware(['auth'])->group(function (): void {
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
