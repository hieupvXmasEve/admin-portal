<?php

use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SemesterEnrollmentController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'semesters',
        controller: SemesterController::class,
        module: 'semesters'
    );

    // API routes for semester activation (using edit permission for safety)
    Route::post('semesters/{semester}/activate', [SemesterController::class, 'activate'])
        ->middleware('can:edit_semester')
        ->name('semesters.activate');
    Route::post('semesters/{semester}/deactivate', [SemesterController::class, 'deactivate'])
        ->middleware('can:edit_semester')
        ->name('semesters.deactivate');
    Route::get('semesters/activation-statuses', [SemesterController::class, 'activationStatuses'])
        ->middleware('can:view_semester')
        ->name('semesters.activation-statuses');

    // Admin enrollment management routes
    Route::prefix('semesters/{semester}')->name('semesters.')->group(function () {
        Route::get('/enrollment', [SemesterEnrollmentController::class, 'show'])
            ->middleware('can:edit_semester')
            ->name('enrollment.show');
    });

    // API routes for enrollment management
    Route::prefix('api/semesters/{semester}')->name('api.semesters.')->group(function () {
        Route::post('/enrollment/generate', [SemesterEnrollmentController::class, 'generateEnrollments'])
            ->middleware('can:edit_semester')
            ->name('enrollment.generate');

        Route::get('/enrollment/suggested-courses', [SemesterEnrollmentController::class, 'getSuggestedCourses'])
            ->middleware('can:edit_semester')
            ->name('enrollment.suggested-courses');

        Route::post('/enrollment/bulk-open-courses', [SemesterEnrollmentController::class, 'bulkOpenCourses'])
            ->middleware('can:edit_semester')
            ->name('enrollment.bulk-open-courses');

        Route::post('/enrollment/open-single-course', [SemesterEnrollmentController::class, 'openSingleCourse'])
            ->middleware('can:edit_semester')
            ->name('enrollment.open-single-course');

        Route::get('/enrollment/stats', [SemesterEnrollmentController::class, 'getRegistrationStats'])
            ->middleware('can:view_semester')
            ->name('enrollment.stats');

        Route::get('/enrollment/registrable-students', [SemesterEnrollmentController::class, 'getRegistrableStudents'])
            ->middleware('can:view_semester')
            ->name('enrollment.registrable-students');

        Route::post('/enrollment/bulk-register', [SemesterEnrollmentController::class, 'bulkRegisterStudents'])
            ->middleware('can:edit_semester')
            ->name('enrollment.bulk-register');
    });
});
