<?php

declare(strict_types=1);

use App\Http\Controllers\Web\CourseRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Admin Course Registrations resource routes
    Route::prefix('course-registrations')->name('course-registrations.')->group(function () {
        Route::get('/', [CourseRegistrationController::class, 'index'])
            ->middleware('can:view_course')
            ->name('index');

        Route::get('/create', [CourseRegistrationController::class, 'create'])
            ->middleware('can:create_course')
            ->name('create');

        Route::post('/', [CourseRegistrationController::class, 'store'])
            ->middleware('can:create_course')
            ->name('store');

        Route::get('/{adminCourseRegistration}', [CourseRegistrationController::class, 'show'])
            ->middleware('can:view_course')
            ->name('show');

        Route::get('/{adminCourseRegistration}/edit', [CourseRegistrationController::class, 'edit'])
            ->middleware('can:edit_course')
            ->name('edit');

        Route::put('/{adminCourseRegistration}', [CourseRegistrationController::class, 'update'])
            ->middleware('can:edit_course')
            ->name('update');

        Route::delete('/{adminCourseRegistration}', [CourseRegistrationController::class, 'destroy'])
            ->middleware('can:delete_course')
            ->name('destroy');

        Route::patch('/{adminCourseRegistration}/drop', [CourseRegistrationController::class, 'drop'])
            ->middleware('can:edit_course')
            ->name('drop');

        Route::patch('/{adminCourseRegistration}/withdraw', [CourseRegistrationController::class, 'withdraw'])
            ->middleware('can:edit_course')
            ->name('withdraw');
    });

    // API routes for course registration operations
    Route::prefix('api/course-registrations')->name('api.course-registrations.')->group(function () {
        Route::delete('/bulk-delete', [CourseRegistrationController::class, 'bulkDestroy'])
            ->middleware('can:delete_course')
            ->name('bulk-delete');

        Route::get('/available-courses', [CourseRegistrationController::class, 'getAvailableCourses'])
            ->middleware('can:view_course')
            ->name('available-courses');

        Route::get('/check-eligibility', [CourseRegistrationController::class, 'checkEligibility'])
            ->middleware('can:view_course')
            ->name('check-eligibility');

        Route::get('/student-registrations', [CourseRegistrationController::class, 'getStudentRegistrations'])
            ->middleware('can:view_course')
            ->name('student-registrations');
    });
});
