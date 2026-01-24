<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Modules\Academic\Http\Web\Admin\StudentAcademicSummaryController;
use App\Modules\Academic\Http\Web\Admin\StudentController;
use App\Modules\Academic\Http\Web\Admin\StudentStatusController;
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
    /**
     * ==========================================================
     * Students (CRUD / Profile)
     * ==========================================================
     */
    Route::prefix('students')->group(function () {

        Route::get('/', [StudentController::class, 'index'])
            ->middleware('can:view_student')
            ->name(StudentRoutes::INDEX);

        Route::get('export', [StudentController::class, 'export'])
            ->middleware('can:view_student')
            ->name(StudentRoutes::EXPORT);

        Route::get('create', [StudentController::class, 'create'])
            ->middleware('can:create_student')
            ->name(StudentRoutes::CREATE);

        Route::post('students', [StudentController::class, 'store'])
            ->middleware('can:create_student')
            ->name(StudentRoutes::STORE);

        Route::get('{student}/edit', [StudentController::class, 'edit'])
            ->middleware('can:edit_student')
            ->name(StudentRoutes::EDIT);

        Route::put('{student}', [StudentController::class, 'update'])
            ->middleware('can:edit_student')
            ->name(StudentRoutes::UPDATE);
    });

    // Photo capture routes
    Route::get('students/{student}/photo-capture', [StudentController::class, 'photoCapture'])
        ->middleware('can:edit_student')
        ->name('students.photo-capture');
    // Student Academic Summary routes
    Route::prefix('students/{student}/academic-summary')->group(function () {

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

        Route::get('/fees', [StudentAcademicSummaryController::class, 'fees'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.fees');


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

    // Student Enrollments & Holds - General Access
    Route::prefix('student-enrollments')->name('student-enrollments.')->group(function () {
        Route::get('/', [StudentStatusController::class, 'enrollmentsIndex'])->name('index');
    });

    // ==========================================================
    // Academic Placement & Progression
    // ==========================================================
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

    Route::prefix('students/{student}/actions')->name('students.actions.')->group(function () {
        Route::get('/', [StudentActionController::class, 'index'])
            ->middleware('can:view_student_action')
            ->name('index');

        Route::post('/', [StudentActionController::class, 'store'])
            ->middleware('can:change_student_status')
            ->name('store');
    });

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
