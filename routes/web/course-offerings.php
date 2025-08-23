<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Web\CourseOfferingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Course Offerings resource routes
    Route::get('course-offerings', [CourseOfferingController::class, 'index'])
        ->middleware('can:view_course_offering')
        ->name(CourseOfferingRoutes::INDEX);

    Route::get('course-offerings/create', [CourseOfferingController::class, 'create'])
        ->middleware('can:create_course_offering')
        ->name(CourseOfferingRoutes::CREATE);

    Route::get('course-offerings/{courseOffering}/edit', [CourseOfferingController::class, 'edit'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::EDIT);

    Route::post('course-offerings', [CourseOfferingController::class, 'store'])
        ->middleware('can:create_course_offering')
        ->name(CourseOfferingRoutes::STORE);

    Route::get('course-offerings/{courseOffering}', [CourseOfferingController::class, 'show'])
        ->middleware('can:view_course_offering')
        ->name(CourseOfferingRoutes::SHOW);

    Route::put('course-offerings/{courseOffering}', [CourseOfferingController::class, 'update'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::UPDATE);

    Route::delete('course-offerings/{courseOffering}', [CourseOfferingController::class, 'destroy'])
        ->middleware('can:delete_course_offering')
        ->name(CourseOfferingRoutes::DESTROY);

    Route::patch('course-offerings/{courseOffering}/toggle-status', [CourseOfferingController::class, 'toggleStatus'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::TOGGLE_STATUS);

    // Split into sections routes
    Route::get('course-offerings/{courseOffering}/split', [CourseOfferingController::class, 'showSplit'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::SPLIT_SHOW);

    Route::post('course-offerings/{courseOffering}/split', [CourseOfferingController::class, 'performSplit'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::SPLIT_PERFORM);

    Route::post('course-offerings/{courseOffering}/duplicate', [CourseOfferingController::class, 'duplicate'])
        ->middleware('can:create_course_offering')
        ->name(CourseOfferingRoutes::DUPLICATE);

    // API routes for bulk operations and statistics
    Route::delete('api/course-offerings/bulk-delete', [CourseOfferingController::class, 'bulkDelete'])
        ->middleware('can:delete_course_offering')
        ->name(CourseOfferingRoutes::API_BULK_DELETE);

    Route::get('api/course-offerings/statistics', [CourseOfferingController::class, 'statistics'])
        ->middleware('can:view_course_offering')
        ->name(CourseOfferingRoutes::API_STATISTICS);

    // Change room for all class sessions of an offering
    Route::post('api/course-offerings/{courseOffering}/change-room', [CourseOfferingController::class, 'changeRoom'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::API_CHANGE_ROOM);

    // Instructor assignment routes
    Route::get('api/course-offerings/check-instructor-assignments', [CourseOfferingController::class, 'checkInstructorAssignments'])
        ->middleware('can:view_course_offering')
        ->name(CourseOfferingRoutes::API_CHECK_INSTRUCTOR_ASSIGNMENTS);

    Route::post('api/course-offerings/bulk-assign-lectures', [CourseOfferingController::class, 'bulkAssignLectures'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::API_BULK_ASSIGN_LECTURES);

    // Registration status management
    Route::post('api/course-offerings/{courseOffering}/bulk-update-status', [CourseOfferingController::class, 'bulkUpdateRegistrationStatus'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::API_BULK_UPDATE_STATUS);

    // Student search and bulk registration
    Route::post('api/course-offerings/{courseOffering}/search-students', [CourseOfferingController::class, 'searchStudents'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::API_SEARCH_STUDENTS);
    
    Route::post('api/course-offerings/{courseOffering}/bulk-register-students', [CourseOfferingController::class, 'bulkRegisterStudents'])
        ->middleware('can:edit_course_offering')
        ->name(CourseOfferingRoutes::API_BULK_REGISTER_STUDENTS);
        
    // Delete individual student registration
    Route::post('course-offerings/{courseOffering}/delete-student-registration', [CourseOfferingController::class, 'deleteStudentRegistration'])
        ->middleware('can:edit_course_offering')
        ->name('course-offerings.delete-student-registration');

    // Class Session Management
    Route::prefix('api/course-offerings/{courseOffering}/class-sessions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ClassSessionController::class, 'index'])
            ->middleware('can:view_course_offering');
        Route::post('/generate', [\App\Http\Controllers\Api\ClassSessionController::class, 'generate'])
            ->middleware('can:edit_course_offering');
        Route::delete('/', [\App\Http\Controllers\Api\ClassSessionController::class, 'destroy'])
            ->middleware('can:edit_course_offering');
    });
});
