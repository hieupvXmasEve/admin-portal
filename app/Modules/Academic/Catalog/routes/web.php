<?php

declare(strict_types=1);

use App\Constants\CurriculumRoutes;
use App\Constants\ProgramRoutes;
use App\Constants\SemesterRoutes;
use App\Constants\UnitRoutes;
use App\Http\Controllers\Web\UnitController as LegacyUnitController;
use App\Modules\Academic\Catalog\Http\Web\AcademicPeriodController;
use App\Modules\Academic\Catalog\Http\Web\CurriculumModuleController;
use App\Modules\Academic\Catalog\Http\Web\CurriculumUnitController;
use App\Modules\Academic\Catalog\Http\Web\ProgramController;
use App\Modules\Academic\Catalog\Http\Web\SelectedAcademicPeriodController;
use App\Modules\Academic\Catalog\Http\Web\UnitController;
use App\Modules\Academic\Catalog\Http\Web\UnitExportController;
use App\Modules\Academic\Catalog\Http\Web\UnitImportController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/syllabus.php';

Route::middleware(['auth', 'web'])->group(function (): void {
    Route::get('semesters', [AcademicPeriodController::class, 'index'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::INDEX);
    Route::post('semesters', [AcademicPeriodController::class, 'store'])
        ->middleware('can:create_semester')
        ->name(SemesterRoutes::STORE);
    Route::put('semesters/{semester}', [AcademicPeriodController::class, 'update'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::UPDATE);
    Route::delete('semesters/{semester}', [AcademicPeriodController::class, 'destroy'])
        ->middleware('can:delete_semester')
        ->name(SemesterRoutes::DESTROY);
    Route::put('api/semesters/{semester}', [AcademicPeriodController::class, 'apiUpdate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_UPDATE);
    Route::post('api/semesters/{semester}/activate', [AcademicPeriodController::class, 'activate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::ACTIVATE);
    Route::post('api/semesters/{semester}/deactivate', [AcademicPeriodController::class, 'deactivate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::DEACTIVATE);
    Route::get('api/semesters/activation-statuses', [AcademicPeriodController::class, 'activationStatuses'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::ACTIVATION_STATUSES);

    Route::post('semester-context', [SelectedAcademicPeriodController::class, 'update'])
        ->middleware('verified')
        ->name('semester-context.update');
    Route::post('finance/semester-context', [SelectedAcademicPeriodController::class, 'update'])
        ->name('finance.semester-context.update');

    Route::get('programs', [ProgramController::class, 'index'])
        ->middleware('can:view_program')
        ->name(ProgramRoutes::INDEX);
    Route::post('programs', [ProgramController::class, 'store'])
        ->middleware('can:create_program')
        ->name(ProgramRoutes::STORE);
    Route::get('programs/{program}', [ProgramController::class, 'show'])
        ->middleware('can:view_program')
        ->name(ProgramRoutes::SHOW);
    Route::put('programs/{program}', [ProgramController::class, 'update'])
        ->middleware('can:edit_program')
        ->name(ProgramRoutes::UPDATE);
    Route::delete('programs/{program}', [ProgramController::class, 'destroy'])
        ->middleware('can:delete_program')
        ->name(ProgramRoutes::DESTROY);

    Route::get('units/export/excel', [UnitExportController::class, 'exportExcelWithCurrentFilters'])
        ->name(UnitRoutes::EXPORT_EXCEL);
    Route::get('units/export/excel/filtered', [UnitExportController::class, 'exportExcelWithCurrentFilters'])
        ->name(UnitRoutes::EXPORT_EXCEL_FILTERED);
    Route::get('units/import', [UnitImportController::class, 'showImportForm'])->name(UnitRoutes::IMPORT);
    Route::post('units/import/upload', [UnitImportController::class, 'uploadFile'])->name(UnitRoutes::IMPORT_UPLOAD);
    Route::post('units/import/preview', [UnitImportController::class, 'previewImport'])->name(UnitRoutes::IMPORT_PREVIEW);
    Route::post('units/import/process', [UnitImportController::class, 'processImport'])->name(UnitRoutes::IMPORT_PROCESS);
    Route::get('units/import/template/{format}', [UnitImportController::class, 'downloadTemplate'])->name(UnitRoutes::IMPORT_TEMPLATE);
    Route::get('units/import/history', [UnitImportController::class, 'getImportHistory'])->name(UnitRoutes::IMPORT_HISTORY);

    Route::get('units', [UnitController::class, 'index'])->middleware('can:view_unit')->name(UnitRoutes::INDEX);
    Route::get('units/create', [UnitController::class, 'create'])->middleware('can:create_unit')->name(UnitRoutes::CREATE);
    Route::get('units/search', [UnitController::class, 'search'])->middleware('can:view_unit')->name('units.search');
    Route::post('units/validate-code', [UnitController::class, 'validateCode'])->name('units.validate-code');
    Route::post('units/validate-prerequisite-expression', [LegacyUnitController::class, 'validatePrerequisiteExpression'])
        ->name('units.validate-prerequisite-expression');
    Route::delete('units/bulk-delete', [UnitController::class, 'bulkDelete'])->name('units.bulk-delete');
    Route::post('units', [UnitController::class, 'store'])->middleware('can:create_unit')->name(UnitRoutes::STORE);
    Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('can:view_unit')->name(UnitRoutes::SHOW);
    Route::get('units/{unit}/edit', [UnitController::class, 'edit'])->middleware('can:edit_unit')->name(UnitRoutes::EDIT);
    Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('can:edit_unit')->name(UnitRoutes::UPDATE);
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('can:delete_unit')->name(UnitRoutes::DESTROY);

    Route::get('curriculum-units', [CurriculumUnitController::class, 'index'])
        ->middleware('can:view_curriculum_unit')->name(CurriculumRoutes::UNIT_INDEX);
    Route::get('curriculum-units/create', [CurriculumUnitController::class, 'create'])
        ->middleware('can:create_curriculum_unit')->name(CurriculumRoutes::UNIT_CREATE);
    Route::post('curriculum-units', [CurriculumUnitController::class, 'store'])
        ->middleware('can:create_curriculum_unit')->name(CurriculumRoutes::UNIT_STORE);
    Route::get('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'show'])
        ->middleware('can:view_curriculum_unit')->name(CurriculumRoutes::UNIT_SHOW);
    Route::get('curriculum-units/{curriculum_unit}/edit', [CurriculumUnitController::class, 'edit'])
        ->middleware('can:edit_curriculum_unit')->name(CurriculumRoutes::UNIT_EDIT);
    Route::put('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'update'])
        ->middleware('can:edit_curriculum_unit')->name(CurriculumRoutes::UNIT_UPDATE);
    Route::delete('curriculum-units/{curriculum_unit}', [CurriculumUnitController::class, 'destroy'])
        ->middleware('can:delete_curriculum_unit')->name(CurriculumRoutes::UNIT_DESTROY);

    Route::post('api/curriculum-units', [CurriculumUnitController::class, 'apiStore'])->name(CurriculumRoutes::API_UNIT_STORE);
    Route::put('api/curriculum-units/{curriculumUnit}', [CurriculumUnitController::class, 'apiUpdate'])->name(CurriculumRoutes::API_UNIT_UPDATE);
    Route::delete('api/curriculum-units/{curriculumUnit}', [CurriculumUnitController::class, 'apiDestroy'])->name(CurriculumRoutes::API_UNIT_DESTROY);
    Route::get('api/curriculum-units/by-curriculum-version', [CurriculumUnitController::class, 'getUnitsByCurriculumVersion'])
        ->name(CurriculumRoutes::API_UNIT_BY_CURRICULUM_VERSION);
    Route::delete('api/curriculum-units/bulk-delete', [CurriculumUnitController::class, 'bulkDelete'])
        ->name(CurriculumRoutes::API_UNIT_BULK_DELETE);

    Route::post('curriculum-versions/{curriculum_version}/modules/attach', [CurriculumModuleController::class, 'attach'])
        ->middleware('can:edit_curriculum_version')
        ->name('curriculum-versions.modules.attach');
    Route::post('curriculum-versions/{curriculum_version}/modules/detach', [CurriculumModuleController::class, 'detach'])
        ->middleware('can:edit_curriculum_version')
        ->name('curriculum-versions.modules.detach');
    Route::put('curriculum-modules/{curriculum_module}', [CurriculumModuleController::class, 'update'])
        ->middleware('can:edit_curriculum_version')
        ->name('curriculum-modules.update');
});
