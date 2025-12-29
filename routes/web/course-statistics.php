<?php

declare(strict_types=1);

use App\Http\Controllers\Web\CourseStatisticsController;
use App\Http\Controllers\Web\UnitStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'campus.selected'])->group(function () {
    Route::get('course-statistics', [CourseStatisticsController::class, 'index'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.index');
    // Statistics for a specific unit
    Route::get('course-statistics/units/{unitId}', [UnitStatisticsController::class, 'show'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.units.show');
    // Statistics for a specific course offering
    Route::get('course-statistics/{courseOffering}', [CourseStatisticsController::class, 'show'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.show');

    Route::get('course-statistics/{courseOffering}/export', [CourseStatisticsController::class, 'export'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.export');

    Route::get('course-statistics/{courseOffering}/assessment-scores', [CourseStatisticsController::class, 'assessmentScores'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.assessment-scores');

    Route::get('course-statistics/{courseOffering}/export-combined', [CourseStatisticsController::class, 'exportCombined'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.export-combined');
});
