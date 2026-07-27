<?php

declare(strict_types=1);

use App\Constants\LectureRoutes;
use App\Modules\Academic\FacultyWorkforce\Http\Web\LectureController;
use App\Modules\Academic\FacultyWorkforce\Http\Web\LectureExportController;
use App\Modules\Academic\FacultyWorkforce\Http\Web\LectureImportController;
use App\Modules\Academic\FacultyWorkforce\Http\Web\LecturerGpaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    // Import routes must come BEFORE resource routes to avoid conflicts

    // Show import form
    Route::get('lectures/import', [LectureImportController::class, 'showImportForm'])
        ->middleware('can:import_lecturer')
        ->name(LectureRoutes::IMPORT_FORM);

    // File upload and preview
    Route::post('lectures/import/upload', [LectureImportController::class, 'uploadFile'])
        ->middleware('can:import_lecturer')
        ->name(LectureRoutes::IMPORT_UPLOAD);

    // Preview import data
    Route::post('lectures/import/preview', [LectureImportController::class, 'previewImport'])
        ->middleware('can:import_lecturer')
        ->name(LectureRoutes::IMPORT_PREVIEW);

    // Process import
    Route::post('lectures/import/process', [LectureImportController::class, 'processImport'])
        ->middleware('can:import_lecturer')
        ->name(LectureRoutes::IMPORT_PROCESS);

    // Template downloads
    Route::get('lectures/templates/{format}', [LectureImportController::class, 'downloadTemplate'])
        ->middleware('can:import_lecturer')
        ->name(LectureRoutes::TEMPLATE_DOWNLOAD)
        ->where('format', 'simple');

    // Export data
    Route::get('lectures/export/excel', [LectureExportController::class, 'exportExcel'])
        ->middleware('can:export_lecturer')
        ->name(LectureRoutes::EXPORT_EXCEL);

    Route::get('lectures/export/excel/filtered', [LectureExportController::class, 'exportExcelWithCurrentFilters'])
        ->middleware('can:export_lecturer')
        ->name(LectureRoutes::EXPORT_EXCEL_FILTERED);

    Route::get('lectures/lecturers-GPA', [LecturerGpaController::class, 'index'])
        ->middleware(['can:view_lecturer', 'can:view_survey_results_aggregate'])
        ->name(LectureRoutes::LECTURER_GPA_INDEX);

    Route::get('lectures/lecturers-GPA/export', [LecturerGpaController::class, 'export'])
        ->middleware(['can:view_lecturer', 'can:view_survey_results_aggregate'])
        ->name(LectureRoutes::LECTURER_GPA_EXPORT);

    // Lecturer resource routes
    Route::get('lectures', [LectureController::class, 'index'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::INDEX);

    Route::get('lectures/create', [LectureController::class, 'create'])
        ->middleware('can:create_lecturer')
        ->name(LectureRoutes::CREATE);

    Route::post('lectures', [LectureController::class, 'store'])
        ->middleware('can:create_lecturer')
        ->name(LectureRoutes::STORE);

    // Teaching Hours Report
    Route::get('lectures/teaching-hours', [LectureController::class, 'teachingHours'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::TEACHING_HOURS);

    Route::get('lectures/teaching-hours/export/excel', [LectureExportController::class, 'exportTeachingHours'])
        ->middleware('can:export_lecturer')
        ->name(LectureRoutes::TEACHING_HOURS_EXPORT);

    Route::get('lectures/teaching-hours/{lecture}', [LectureController::class, 'showTeachingHours'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::TEACHING_HOURS_DETAILS);

    Route::get('lectures/{lecture}', [LectureController::class, 'show'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::SHOW);

    Route::get('lectures/{lecture}/edit', [LectureController::class, 'edit'])
        ->middleware('can:edit_lecturer')
        ->name(LectureRoutes::EDIT);

    Route::put('lectures/{lecture}', [LectureController::class, 'update'])
        ->middleware('can:edit_lecturer')
        ->name(LectureRoutes::UPDATE);

    Route::delete('lectures/{lecture}', [LectureController::class, 'destroy'])
        ->middleware('can:delete_lecturer')
        ->name(LectureRoutes::DESTROY);

    // API routes for dropdown/select usage and search
    Route::get('api/lectures/search', [LectureController::class, 'apiSearch'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::API_SEARCH);

    Route::get('api/lectures/statistics', [LectureController::class, 'statistics'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::API_STATISTICS);
});
