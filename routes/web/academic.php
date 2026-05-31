<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use App\Modules\Academic\Http\Web\GpaHistoryController;
use App\Modules\Academic\Http\Web\PerformanceDashboardController;
use App\Modules\Academic\Http\Web\WarningCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('academic')->name('academic.')->group(function () {
    Route::prefix('gpa')->name('gpa.')->group(function () {
        Route::get('/finalize', [GpaManagementController::class, 'index'])
            ->middleware('can:view_gpa_finalization')
            ->name('finalize.index');
        Route::post('/finalize', [GpaManagementController::class, 'finalize'])
            ->middleware('can:create_gpa_finalization')
            ->name('finalize.store');

        // History & Export
        Route::get('/history', [GpaHistoryController::class, 'index'])
            ->middleware('can:view_gpa_history')
            ->name('history');
        Route::get('/history/export', [GpaHistoryController::class, 'export'])
            ->middleware('can:view_gpa_history')
            ->name('history.export');
    });

    // Performance Dashboard
    Route::get('/students/performance', [PerformanceDashboardController::class, 'index'])
        ->middleware('can:view_performance_dashboard')
        ->name('students.performance');

    Route::prefix('warnings')->name('warnings.')->group(function () {
        Route::get('/', [WarningCenterController::class, 'index'])
            ->name('index');
        Route::get('/settings', [WarningCenterController::class, 'settings'])
            ->name('settings');
        Route::put('/settings', [WarningCenterController::class, 'updateSettings'])
            ->name('settings.update');
        Route::post('/academic-standing/{gpaCalculation}/send', [WarningCenterController::class, 'sendAcademicStanding'])
            ->name('academic-standing.send');
        Route::post('/attendance/{courseOffering}/{student}/send', [WarningCenterController::class, 'sendAttendance'])
            ->name('attendance.send');
    });

    Route::get('/report', [AcademicReportController::class, 'index'])
        ->middleware('can:view_academic_report')
        ->name('report.index');

    Route::get('/course-ranking', [CourseRankingController::class, 'index'])
        ->middleware('can:view_academic_report')
        ->name('course-ranking.index');
});
