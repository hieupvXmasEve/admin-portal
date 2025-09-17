<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Http\Controllers\Web\AcademicRecordController;
use App\Http\Controllers\Web\AcademicStandingController;
use App\Http\Controllers\Web\CourseRetakeController;
use App\Http\Controllers\Web\ProgramChangeController;
use App\Http\Controllers\Web\StudentAcademicSummaryController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\StudentStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Management Routes
|--------------------------------------------------------------------------
|
| Routes for advanced student management features including academic records,
| program changes, course retakes, academic standing, and status tracking.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // Student management routes
    Route::get('students', [StudentController::class, 'index'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::INDEX);

    // Export route (must come before {student} routes)
    Route::get('students/export', [StudentController::class, 'export'])
        ->middleware('can:view_student')
        ->name('students.export');

    Route::get('students/create', [StudentController::class, 'create'])
        ->middleware('can:create_student')
        ->name(StudentRoutes::CREATE);

    Route::post('students', [StudentController::class, 'store'])
        ->middleware('can:create_student')
        ->name(StudentRoutes::STORE);

    Route::get('students/{student}/edit', [StudentController::class, 'edit'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::EDIT);

    Route::put('students/{student}', [StudentController::class, 'update'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::UPDATE);

    // Photo capture routes
    Route::get('students/{student}/photo-capture', [StudentController::class, 'photoCapture'])
        ->middleware('can:edit_student')
        ->name('students.photo-capture');

    Route::delete('students/{student}', [StudentController::class, 'destroy'])
        ->middleware('can:delete_student')
        ->name(StudentRoutes::DESTROY);

    // Additional student management actions
//    Route::post('students/{student}/assign-program', [StudentController::class, 'assignProgram'])
//        ->middleware('can:edit_student')
//        ->name(StudentRoutes::ASSIGN_PROGRAM);

    Route::post('students/{student}/update-status', [StudentController::class, 'updateStatus'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::UPDATE_STATUS);

    // Student Academic Summary routes
    Route::prefix('students/{student}/academic-summary')->group(function () {

        Route::get('/', [StudentAcademicSummaryController::class, 'show'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_SHOW);

        Route::get('/filter-by-semester', [StudentAcademicSummaryController::class, 'filterBySemester'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_SEMESTER);

        Route::get('/filter-by-course-offering', [StudentAcademicSummaryController::class, 'filterByCourseOffering'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_COURSE_OFFERING);

        Route::get('/attendance-details', [StudentAcademicSummaryController::class, 'getAttendanceDetails'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_ATTENDANCE_DETAILS);

        Route::get('/score-details', [StudentAcademicSummaryController::class, 'getScoreDetails'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_SCORE_DETAILS);

        Route::get('/course-scores/{courseOfferingId}', [StudentAcademicSummaryController::class, 'getCourseScores'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_COURSE_SCORES);
    });

    // Academic Records Management - General Access (for menu)
    Route::prefix('academic-records')->name('academic-records.')->group(function () {
        Route::get('/', [AcademicRecordController::class, 'globalIndex'])->name('index');
        Route::get('/analytics', [AcademicRecordController::class, 'analytics'])->name('analytics');
    });

    // Academic Records Management - Student Specific
    Route::prefix('students/{student}/academic-records')->name('students.academic-records.')->group(function () {
        Route::get('/', [AcademicRecordController::class, 'index'])->name('index');
        Route::get('/{record}', [AcademicRecordController::class, 'show'])->name('show');
        Route::post('/', [AcademicRecordController::class, 'store'])->name('store');
        Route::get('/transcript/view', [AcademicRecordController::class, 'transcript'])->name('transcript');
        Route::get('/gpa/history', [AcademicRecordController::class, 'gpaHistory'])->name('gpa-history');
    });

    // Program Change Requests
    Route::prefix('program-changes')->name('program-changes.')->group(function () {
        Route::get('/', [ProgramChangeController::class, 'index'])->name('index');
        Route::get('/{programChangeRequest}', [ProgramChangeController::class, 'show'])->name('show');
        Route::patch('/{programChangeRequest}/approve', [ProgramChangeController::class, 'approve'])->name('approve');
        Route::patch('/{programChangeRequest}/reject', [ProgramChangeController::class, 'reject'])->name('reject');
    });

    Route::prefix('students/{student}/program-changes')->name('students.program-changes.')->group(function () {
        Route::get('/create', [ProgramChangeController::class, 'create'])->name('create');
        Route::post('/', [ProgramChangeController::class, 'store'])->name('store');
        Route::get('/{programChangeRequest}', [ProgramChangeController::class, 'show'])->name('show');
        Route::post('/evaluate-credits', [ProgramChangeController::class, 'evaluateCredits'])->name('evaluate-credits');
    });

    // Course Retakes - General Access (for menu)
    Route::prefix('course-retakes')->name('course-retakes.')->group(function () {
        Route::get('/', [CourseRetakeController::class, 'globalIndex'])->name('index');
        Route::get('/statistics', [CourseRetakeController::class, 'statistics'])->name('statistics');
    });

    // Course Retakes - Student Specific
    Route::prefix('students/{student}/retakes')->name('students.retakes.')->group(function () {
        Route::get('/', [CourseRetakeController::class, 'index'])->name('index');
        Route::get('/create', [CourseRetakeController::class, 'create'])->name('create');
        Route::post('/', [CourseRetakeController::class, 'store'])->name('store');
        Route::get('/{registration}', [CourseRetakeController::class, 'show'])->name('show');
    });

    // Academic Standing
    Route::prefix('students/{student}/standing')->name('students.standing.')->group(function () {
        Route::get('/', [AcademicStandingController::class, 'index'])->name('index');
        Route::get('/create', [AcademicStandingController::class, 'create'])->name('create');
        Route::post('/', [AcademicStandingController::class, 'store'])->name('store');
        Route::get('/{standing}', [AcademicStandingController::class, 'show'])->name('show');
    });

    Route::prefix('academic-standings')->name('academic-standings.')->group(function () {
        Route::get('/', [AcademicStandingController::class, 'globalIndex'])->name('index');
        Route::post('/bulk-update', [AcademicStandingController::class, 'bulkUpdate'])->name('bulk-update');
    });

    // Student Enrollments & Holds - General Access
    Route::prefix('student-enrollments')->name('student-enrollments.')->group(function () {
        Route::get('/', [StudentStatusController::class, 'enrollmentsIndex'])->name('index');
        Route::get('/holds', [StudentStatusController::class, 'holdsIndex'])->name('holds');
    });

    // Student Status Tracking
    Route::prefix('students/status-tracking')->name('students.status.')->group(function () {
        Route::get('/', [StudentStatusController::class, 'index'])->name('index');
        Route::get('/statistics', [StudentStatusController::class, 'statistics'])->name('statistics');
    });

    Route::prefix('students/{student}/status')->name('students.status.')->group(function () {
        Route::get('/', [StudentStatusController::class, 'show'])->name('show');
        Route::patch('/update', [StudentStatusController::class, 'update'])->name('update');
        Route::get('/history', [StudentStatusController::class, 'history'])->name('history');
    });

    // Grade Distribution API
    Route::get('/units/{unit}/grade-distribution', [AcademicRecordController::class, 'gradeDistribution'])
        ->name('units.grade-distribution');
});
