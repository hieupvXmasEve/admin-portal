<?php

declare(strict_types=1);

use App\Constants\ProgramRoutes;
use App\Constants\SemesterRoutes;
use App\Constants\UnitRoutes;
use App\Http\Controllers\Web\UnitController as LegacyUnitController;
use App\Http\Controllers\Web\Units\UnitExportController;
use App\Http\Controllers\Web\Units\UnitImportController;
use App\Modules\Academic\Catalog\Http\Web\AcademicPeriodController;
use App\Modules\Academic\Catalog\Http\Web\ProgramController;
use App\Modules\Academic\Catalog\Http\Web\SelectedAcademicPeriodController;
use App\Modules\Academic\Catalog\Http\Web\UnitController;
use Illuminate\Support\Facades\Route;

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
});
