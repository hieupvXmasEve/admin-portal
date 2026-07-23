<?php

use App\Constants\SemesterRoutes;
use App\Http\Controllers\Web\SemesterEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Admin enrollment management routes
    Route::get('semesters/{semester}/enrollment', [SemesterEnrollmentController::class, 'show'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::ENROLLMENT_SHOW);

    // API routes for enrollment management
    Route::post('api/semesters/{semester}/enrollment/generate', [SemesterEnrollmentController::class, 'generateEnrollments'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_GENERATE);

    Route::get('api/semesters/{semester}/enrollment/suggested-courses', [SemesterEnrollmentController::class, 'getSuggestedCourses'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_SUGGESTED_COURSES);

    Route::post('api/semesters/{semester}/enrollment/bulk-open-courses', [SemesterEnrollmentController::class, 'bulkOpenCourses'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_BULK_OPEN_COURSES);

    Route::post('api/semesters/{semester}/enrollment/open-single-course', [SemesterEnrollmentController::class, 'openSingleCourse'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_OPEN_SINGLE_COURSE);

    Route::get('api/semesters/{semester}/enrollment/stats', [SemesterEnrollmentController::class, 'getRegistrationStats'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_STATS);

    Route::get('api/semesters/{semester}/enrollment/registrable-students', [SemesterEnrollmentController::class, 'getRegistrableStudents'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_REGISTRABLE_STUDENTS);

    Route::post('api/semesters/{semester}/enrollment/bulk-register', [SemesterEnrollmentController::class, 'bulkRegisterStudents'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_BULK_REGISTER);
});
