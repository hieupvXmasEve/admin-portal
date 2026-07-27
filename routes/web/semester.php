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
});
