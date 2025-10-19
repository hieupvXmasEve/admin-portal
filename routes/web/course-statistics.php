<?php

declare(strict_types=1);

use App\Http\Controllers\Web\CourseStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'campus.selected'])->group(function () {
    Route::get('course-statistics', [CourseStatisticsController::class, 'index'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.index');

    Route::get('course-statistics/{courseOffering}', [CourseStatisticsController::class, 'show'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.show');

    Route::get('course-statistics/{courseOffering}/export', [CourseStatisticsController::class, 'export'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.export');

    Route::get('course-statistics/{courseOffering}/assessment-scores', [CourseStatisticsController::class, 'assessmentScores'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.assessment-scores');
});
