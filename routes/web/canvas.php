<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Canvas\CanvasIntegrationController;
use App\Http\Controllers\Web\Canvas\CanvasOAuthController;
use App\Http\Controllers\Web\Canvas\CanvasSyncController;
use App\Http\Controllers\Web\Canvas\CanvasCourseController;
use App\Http\Controllers\Web\Canvas\CanvasSyllabusController;
use Illuminate\Support\Facades\Route;

// 'canvas' => [
//             'view_canvas_integration' => 'view_canvas_integration',
//             'create_canvas_integration' => 'create_canvas_integration',
//             'edit_canvas_integration' => 'edit_canvas_integration',
//             'delete_canvas_integration' => 'delete_canvas_integration',
//             'sync_canvas_courses' => 'sync_canvas_courses',
//             'map_canvas_courses' => 'map_canvas_courses',
//         ],
Route::prefix('admin/canvas')
    ->middleware(['auth', 'verified', 'campus.selected'])
    ->name('admin.canvas.')
    ->group(function () {
        // Canvas Integrations
        Route::get('/integrations', [CanvasIntegrationController::class, 'index'])
            ->middleware('can:view_canvas_integration')
            ->name('integrations.index');

        Route::post('/integrations', [CanvasIntegrationController::class, 'store'])
            ->middleware('can:create_canvas_integration')
            ->name('integrations.store');

        Route::delete('/integrations/{integration}', [CanvasIntegrationController::class, 'destroy'])
            ->middleware('can:delete_canvas_integration')
            ->name('integrations.destroy');

        Route::post('/integrations/{integration}/toggle', [CanvasIntegrationController::class, 'toggleActive'])
            ->middleware('can:edit_canvas_integration')
            ->name('integrations.toggle');

        // OAuth Flow
        Route::get('/oauth/redirect/{integration}', [CanvasOAuthController::class, 'redirect'])
            ->name('oauth.redirect');

        Route::get('/oauth/callback', [CanvasOAuthController::class, 'callback'])
            ->name('oauth.callback');

        Route::post('/oauth/revoke/{integration}', [CanvasOAuthController::class, 'revoke'])
            ->name('oauth.revoke');

        // Sync
        Route::post('/sync/{integration}', [CanvasSyncController::class, 'syncCourses'])
            ->name('sync.courses');

        // Canvas Courses
        Route::get('/courses', [CanvasCourseController::class, 'index'])
            ->name('courses.index');

        Route::post('/courses/map', [CanvasCourseController::class, 'mapCourse'])
            ->name('courses.map');

        Route::post('/courses/{mapping}/unmap', [CanvasCourseController::class, 'unmapCourse'])
            ->name('courses.unmap');

        Route::post('/courses/{mapping}/ignore', [CanvasCourseController::class, 'ignoreCourse'])
            ->name('courses.ignore');

        // API endpoint for course offerings dropdown
        Route::get('/api/course-offerings', [CanvasCourseController::class, 'getAvailableCourseOfferings'])
            ->name('api.course-offerings');

        // Syllabus sync
        Route::get('/courses/{mapping}/sync-summary', [CanvasSyllabusController::class, 'getSyncSummary'])
            ->name('courses.sync-summary');

        // Assignment sync
        Route::get('/courses/{mapping}/assignments/sync-summary', [CanvasSyllabusController::class, 'getAssignmentSyncSummary'])
            ->name('courses.assignments.sync-summary');
        Route::post('/courses/{mapping}/sync-assignments', [CanvasSyllabusController::class, 'syncAssignments'])
            ->name('courses.sync-assignments');

        // Grade sync
        Route::get('/courses/{mapping}/grades/sync-summary', [CanvasSyllabusController::class, 'getGradeSyncSummary'])
            ->name('courses.grades.sync-summary');
        Route::post('/courses/{mapping}/sync-grades', [CanvasSyllabusController::class, 'syncGrades'])
            ->name('courses.sync-grades');
    });
