<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Modules\Academic\Http\Web\Canvas\PreviewCanvasGradeSyncController;
use App\Modules\Academic\Http\Web\Canvas\SyncCanvasGradeController;
use App\Modules\Academic\Http\Web\FinalizeCourseOfferingController;
use App\Modules\Academic\Http\Web\RecalculateApplyController;
use App\Modules\Academic\Http\Web\RecalculatePreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('course-offerings')->group(function () {
        // ============================================
        // CRUD Resource Routes
        // ============================================
        Route::post('/{courseOffering}/finalize', FinalizeCourseOfferingController::class)
            ->middleware('can:complete_course_offering')
            ->name(CourseOfferingRoutes::FINALIZE);

        // ============================================
        // Split & Duplicate Operations
        // ============================================
        // ============================================
        // Student Registration Management
        // ============================================
    });

    Route::prefix('api/course-offerings')->group(function () {
        // ============================================
        // Student Registration Management (API)
        // ============================================
        // ============================================
        // Instructor Assignment
        // ============================================
        // ============================================
        // Bulk Operations & Statistics
        // ============================================
        // ============================================
        // Canvas Grade Sync (cockpit Scores tab, issue 10)
        // ============================================
        Route::post('/{courseOffering}/sync-grades/preview', PreviewCanvasGradeSyncController::class)
            ->middleware('can:sync_course_grades')
            ->name(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW);

        Route::post('/{courseOffering}/sync-grades', SyncCanvasGradeController::class)
            ->middleware('can:sync_course_grades')
            ->name(CourseOfferingRoutes::API_SYNC_GRADES_APPLY);

        // ============================================
        // Recalculate preview + apply (cockpit Scores tab, issue 11) — gated
        // solely by recalculate_course_offering (ADR 0014); the embedded
        // Canvas pull does not additionally require sync_course_grades.
        // ============================================
        Route::post('/{courseOffering}/recalculate/preview', RecalculatePreviewController::class)
            ->middleware('can:recalculate_course_offering')
            ->name(CourseOfferingRoutes::API_RECALCULATE_PREVIEW);

        Route::post('/{courseOffering}/recalculate/apply', RecalculateApplyController::class)
            ->middleware('can:recalculate_course_offering')
            ->name(CourseOfferingRoutes::API_RECALCULATE_APPLY);

    });
});
