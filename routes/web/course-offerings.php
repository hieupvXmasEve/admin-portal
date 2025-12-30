<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Web\CourseOfferingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('course-offerings')->group(function () {
        // ============================================
        // CRUD Resource Routes
        // ============================================
        Route::get('/', [CourseOfferingController::class, 'index'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::INDEX);

        Route::get('/create', [CourseOfferingController::class, 'create'])
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::CREATE);

        Route::post('/', [CourseOfferingController::class, 'store'])
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::STORE);

        Route::get('/{courseOffering}', [CourseOfferingController::class, 'show'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::SHOW);

        Route::get('/{courseOffering}/edit', [CourseOfferingController::class, 'edit'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::EDIT);

        Route::put('/{courseOffering}', [CourseOfferingController::class, 'update'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::UPDATE);

        Route::delete('/{courseOffering}', [CourseOfferingController::class, 'destroy'])
            ->middleware('can:delete_course_offering')
            ->name(CourseOfferingRoutes::DESTROY);

        Route::patch('/{courseOffering}/toggle-status', [CourseOfferingController::class, 'toggleStatus'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::TOGGLE_STATUS);

        Route::patch('/{courseOffering}/update-course-status', [CourseOfferingController::class, 'updateCourseStatus'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::UPDATE_COURSE_STATUS);

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

        Route::post('/{courseOffering}/duplicate', [CourseOfferingController::class, 'duplicate'])
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::DUPLICATE);

        // ============================================
        // Student Registration Management
        // ============================================
        Route::post('/{courseOffering}/delete-student-registration', [CourseOfferingController::class, 'deleteStudentRegistration'])
            ->middleware('can:delete_student_registration')
            ->name('course-offerings.delete-student-registration');
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

        Route::post('/{courseOffering}/bulk-update-status', [CourseOfferingController::class, 'bulkUpdateRegistrationStatus'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_BULK_UPDATE_STATUS);

        // ============================================
        // Instructor Assignment
        // ============================================
        Route::get('/check-instructor-assignments', [CourseOfferingController::class, 'checkInstructorAssignments'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::API_CHECK_INSTRUCTOR_ASSIGNMENTS);

        Route::post('/bulk-assign-lectures', [CourseOfferingController::class, 'bulkAssignLectures'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_BULK_ASSIGN_LECTURES);

        // ============================================
        // Bulk Operations & Statistics
        // ============================================
        Route::delete('/bulk-delete', [CourseOfferingController::class, 'bulkDelete'])
            ->middleware('can:delete_course_offering')
            ->name(CourseOfferingRoutes::API_BULK_DELETE);

        Route::get('/statistics', [CourseOfferingController::class, 'statistics'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::API_STATISTICS);

        Route::post('/{courseOffering}/change-room', [CourseOfferingController::class, 'changeRoom'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_CHANGE_ROOM);

        // ============================================
        // Class Session Management
        // ============================================
        Route::prefix('/{courseOffering}/class-sessions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ClassSessionController::class, 'index'])
                ->middleware('can:view_course_offering');
            Route::post('/generate', [\App\Http\Controllers\Api\ClassSessionController::class, 'generate'])
                ->middleware('can:edit_course_offering');
            Route::post('/bulk-update', [CourseOfferingController::class, 'bulkUpdateClassSessions'])
                ->middleware('can:edit_course_offering');
            Route::delete('/', [\App\Http\Controllers\Api\ClassSessionController::class, 'destroy'])
                ->middleware('can:edit_course_offering');
        });
    });

    // ============================================
    // Individual Class Session Management (API)
    // ============================================
    Route::post('api/class-sessions', [\App\Http\Controllers\Api\ClassSessionController::class, 'store'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.store');

    Route::delete('api/class-sessions/bulk', [\App\Http\Controllers\Api\ClassSessionController::class, 'bulkDestroy'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.bulk-destroy');

    Route::delete('api/class-sessions/{classSession}', [\App\Http\Controllers\Api\ClassSessionController::class, 'destroySingle'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.destroy');
});
