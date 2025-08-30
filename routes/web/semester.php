<?php

use App\Constants\SemesterRoutes;
use App\Http\Controllers\Web\SemesterController;
use App\Http\Controllers\Web\SemesterEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('semesters', [SemesterController::class, 'index'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::INDEX);
    Route::get('semesters/create', [SemesterController::class, 'create'])
        ->middleware('can:create_semester')
        ->name(SemesterRoutes::CREATE);
    Route::post('semesters', [SemesterController::class, 'store'])
        ->middleware('can:create_semester')
        ->name(SemesterRoutes::STORE);
    // Detail
    Route::get('semesters/{semester}', [SemesterController::class, 'show'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::SHOW);

    Route::get('semesters/{semester}/edit', [SemesterController::class, 'edit'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::EDIT);
    Route::put('semesters/{semester}', [SemesterController::class, 'update'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::UPDATE);
    Route::delete('semesters/{semester}', [SemesterController::class, 'destroy'])
        ->middleware('can:delete_semester')
        ->name(SemesterRoutes::DESTROY);

    // API semester update
    Route::put('api/semesters/{semester}', [SemesterController::class, 'apiUpdate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_UPDATE);

    // API routes for semester activation (using edit permission for safety)
    Route::post('api/semesters/{semester}/activate', [SemesterController::class, 'activate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::ACTIVATE);
    Route::post('api/semesters/{semester}/deactivate', [SemesterController::class, 'deactivate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::DEACTIVATE);
    Route::get('api/semesters/activation-statuses', [SemesterController::class, 'activationStatuses'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::ACTIVATION_STATUSES);

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
