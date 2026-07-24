<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Web\CourseOfferingController;
use App\Modules\Academic\Http\Web\Canvas\PreviewCanvasGradeSyncController;
use App\Modules\Academic\Http\Web\Canvas\SyncCanvasGradeController;
use App\Modules\Academic\Http\Web\FinalizeCourseOfferingController;
use App\Modules\Academic\Http\Web\RecalculateApplyController;
use App\Modules\Academic\Http\Web\RecalculatePreviewController;
use App\Modules\Academic\Http\Web\RecordClassSessionAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('course-offerings')->group(function () {
        // ============================================
        // CRUD Resource Routes
        // ============================================
        Route::get('/', [CourseOfferingController::class, 'index'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::INDEX);

        Route::get('/{courseOffering}', [CourseOfferingController::class, 'show'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::SHOW);

        Route::post('/{courseOffering}/finalize', FinalizeCourseOfferingController::class)
            ->middleware('can:complete_course_offering')
            ->name(CourseOfferingRoutes::FINALIZE);

        // Cockpit attendance recording (ADR 0013 phase B) — same permission
        // as the standalone AttendanceController::store endpoint it replaces.
        Route::post('/{courseOffering}/class-sessions/{classSession}/record-attendance', RecordClassSessionAttendanceController::class)
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::RECORD_SESSION_ATTENDANCE);

        Route::post('/{courseOffering}/survey', [CourseOfferingController::class, 'createSurvey'])
            ->middleware('can:edit_course_offering')
            ->name('course-offerings.create-survey');

        // ============================================
        // Split & Duplicate Operations
        // ============================================
        Route::get('/{courseOffering}/split', [CourseOfferingController::class, 'showSplit'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::SPLIT_SHOW);

        Route::post('/{courseOffering}/split', [CourseOfferingController::class, 'performSplit'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::SPLIT_PERFORM);

        // ============================================
        // Student Registration Management
        // ============================================
    });

    Route::prefix('api/course-offerings')->group(function () {
        // ============================================
        // Student Registration Management (API)
        // ============================================
        Route::post('/{courseOffering}/search-students', [CourseOfferingController::class, 'searchStudents'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_SEARCH_STUDENTS);

        Route::post('/{courseOffering}/bulk-register-students', [CourseOfferingController::class, 'bulkRegisterStudents'])
            ->middleware('can:add_student_registration')
            ->name(CourseOfferingRoutes::API_BULK_REGISTER_STUDENTS);

        // ============================================
        // Instructor Assignment
        // ============================================
        Route::get('/check-instructor-assignments', [CourseOfferingController::class, 'checkInstructorAssignments'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::API_CHECK_INSTRUCTOR_ASSIGNMENTS);

        // ============================================
        // Bulk Operations & Statistics
        // ============================================
        Route::get('/statistics', [CourseOfferingController::class, 'statistics'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::API_STATISTICS);

        Route::post('/{courseOffering}/change-room', [CourseOfferingController::class, 'changeRoom'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_CHANGE_ROOM);

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

        // ============================================
        // Class Session Management
        // ============================================
        Route::prefix('/{courseOffering}/class-sessions')->group(function () {
            Route::get('/', [ClassSessionController::class, 'index'])
                ->middleware('can:view_course_offering');
            Route::post('/generate', [ClassSessionController::class, 'generate'])
                ->middleware('can:edit_course_offering');
            Route::post('/bulk-update', [CourseOfferingController::class, 'bulkUpdateClassSessions'])
                ->middleware('can:edit_course_offering')
                ->name('course-offerings.bulk-update-class-sessions');
            Route::delete('/', [ClassSessionController::class, 'destroy'])
                ->middleware('can:edit_course_offering');
        });
    });

    // ============================================
    // Individual Class Session Management (API)
    // ============================================
    Route::post('api/class-sessions', [ClassSessionController::class, 'store'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.store');

    Route::delete('api/class-sessions/bulk', [ClassSessionController::class, 'bulkDestroy'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.bulk-destroy');

    Route::delete('api/class-sessions/{classSession}', [ClassSessionController::class, 'destroySingle'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.destroy');
});
