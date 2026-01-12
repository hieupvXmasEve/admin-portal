<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Http\Controllers\Web\AcademicRecordController;
use App\Http\Controllers\Web\AcademicStandingController;
use App\Http\Controllers\Web\ProgramChangeController;
use App\Http\Controllers\Web\StudentAcademicSummaryController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\StudentStatusController;
use App\Modules\Academic\Http\Web\Admin\AcademicPlacementController;
use App\Modules\Academic\Http\Web\Admin\AcademicProgressionAuditController;
use App\Modules\Academic\Http\Web\Admin\StudentActionAuditController;
use App\Modules\Academic\Http\Web\Admin\StudentActionController;
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

    // Route::delete('students/{student}', [StudentController::class, 'destroy'])
    //     ->middleware('can:delete_student')
    //     ->name(StudentRoutes::DESTROY);

    // Additional student management actions
    //    Route::post('students/{student}/assign-program', [StudentController::class, 'assignProgram'])
    //        ->middleware('can:edit_student')
    //        ->name(StudentRoutes::ASSIGN_PROGRAM);

    Route::post('students/{student}/update-status', [StudentController::class, 'updateStatus'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::UPDATE_STATUS);

    Route::post('students/{student}/change-status-gc-level', [StudentController::class, 'changeStatusAndGcLevel'])
        ->middleware('can:change_student_status')
        ->name('students.change-status-gc-level');

    // Student Academic Summary routes
    Route::prefix('students/{student}/academic-summary')->group(function () {

        Route::get('/', [StudentAcademicSummaryController::class, 'show'])
            ->middleware('can:view_student_summary')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_SHOW);

        // Individual tab routes for partial loading
        Route::get('/overview', [StudentAcademicSummaryController::class, 'overview'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.overview');

        Route::get('/registrations', [StudentAcademicSummaryController::class, 'registrations'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.registrations');

        Route::get('/scores', [StudentAcademicSummaryController::class, 'scores'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.scores');

        Route::get('/attendance', [StudentAcademicSummaryController::class, 'attendance'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.attendance');

        Route::get('/gpa', [StudentAcademicSummaryController::class, 'gpa'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.gpa');

        Route::get('/graduation', [StudentAcademicSummaryController::class, 'graduation'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.graduation');

        Route::get('/gold', [StudentAcademicSummaryController::class, 'gold'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.gold');

        Route::get('/wallet', [StudentAcademicSummaryController::class, 'wallet'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.wallet');

        Route::get('/tuition-plan', [StudentAcademicSummaryController::class, 'tuitionPlan'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.tuition-plan');

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

    // ==========================================================
    // Academic Placement & Progression
    // ==========================================================

    // Student Placement & Progression (within student context)
    Route::prefix('students/{student}/placement')->name('students.placement.')->group(function () {
        Route::get('/', [AcademicPlacementController::class, 'show'])
            ->middleware('can:view_student')
            ->name('index');

        Route::post('/initialize', [AcademicPlacementController::class, 'initializePlacement'])
            ->middleware('can:change_student_status')
            ->name('initialize');

        Route::post('/ielts', [AcademicPlacementController::class, 'recordIelts'])
            ->middleware('can:change_student_status')
            ->name('ielts.store');

        Route::post('/level', [AcademicPlacementController::class, 'updateLevel'])
            ->middleware('can:change_student_status')
            ->name('level.update');

        Route::post('/transition', [AcademicPlacementController::class, 'transitionToIntake'])
            ->middleware('can:change_student_status')
            ->name('transition');
    });

    // IELTS Certificate document upload
    Route::post('/ielts-certificates/{certificate}/document', [AcademicPlacementController::class, 'uploadIeltsDocument'])
        ->middleware('can:change_student_status')
        ->name('students.ielts-certificates.document.upload');

    // Academic Progression Audit Reports
    Route::prefix('reports/academic-progression')->name('reports.academic-progression.')->group(function () {
        Route::get('/', [AcademicProgressionAuditController::class, 'index'])
            ->middleware('can:view_student_action')
            ->name('index');

        Route::get('/missing-documents', [AcademicProgressionAuditController::class, 'missingDocuments'])
            ->middleware('can:view_student_action')
            ->name('missing-documents');

        Route::get('/export', [AcademicProgressionAuditController::class, 'export'])
            ->middleware('can:view_student_action')
            ->name('export');
    });

    // ==========================================================
    // Student Administrative Actions (Audit & Status Changes)
    // ==========================================================

    // Student Action History (within student context)
    Route::prefix('students/{student}/actions')->name('students.actions.')->group(function () {
        Route::get('/', [StudentActionController::class, 'index'])
            ->middleware('can:view_student_action')
            ->name('index');

        Route::post('/', [StudentActionController::class, 'store'])
            ->middleware('can:change_student_status')
            ->name('store');
    });

    // Individual action log routes


    // Student Actions Audit/Reports
    Route::prefix('reports/student-actions')->name('reports.student-actions.')->group(function () {
        Route::get('/', [StudentActionAuditController::class, 'index'])
            ->middleware('can:view_student_action')
            ->name('index');

        Route::get('/export', [StudentActionAuditController::class, 'export'])
            ->middleware('can:view_student_action')
            ->name('export');

        Route::get('/{actionLog}', [StudentActionController::class, 'show'])
            ->middleware('can:view_student_action')
            ->name('show');

        Route::put('/{actionLog}', [StudentActionController::class, 'update'])
            ->middleware('can:change_student_status')
            ->name('update');

        Route::post('/{actionLog}/attachments', [StudentActionController::class, 'uploadAttachment'])
            ->middleware('can:change_student_status')
            ->name('attachments.store');
    });
});
