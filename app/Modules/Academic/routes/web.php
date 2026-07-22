<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Constants\StudentRoutes;
use App\Modules\Academic\Http\Web\AcademicPlacementController;
use App\Modules\Academic\Http\Web\AcademicProgressionAuditController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingCatalogFormController;
use App\Modules\Academic\Http\Web\CampusDetailController;
use App\Modules\Academic\Http\Web\ExamResitAttemptController;
use App\Modules\Academic\Http\Web\ExamScheduleController;
use App\Modules\Academic\Http\Web\RetakeCourseRegistrationController;
use App\Modules\Academic\Http\Web\StudentAcademicSummaryController;
use App\Modules\Academic\Http\Web\StudentActionAuditController;
use App\Modules\Academic\Http\Web\StudentActionController;
use App\Modules\Academic\Http\Web\StudentController;
use App\Modules\Academic\Http\Web\StudentDecisionController;
use App\Modules\Academic\Http\Web\StudentLifecycleYearlyAnalysisController;
use App\Modules\Academic\Http\Web\StudentStatusController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../Catalog/routes/web.php';

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
    Route::prefix('course-offerings')->group(function () {
        Route::get('/create', [CourseOfferingCatalogFormController::class, 'create'])
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::CREATE);

        Route::post('/', [CourseOfferingCatalogFormController::class, 'store'])
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::STORE);

        Route::get('/{courseOffering}/edit', [CourseOfferingCatalogFormController::class, 'edit'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::EDIT);

        Route::put('/{courseOffering}', [CourseOfferingCatalogFormController::class, 'update'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::UPDATE);
    });

    Route::get('campuses/{campus}', [CampusDetailController::class, 'show'])
        ->middleware('can:view_campus')
        ->name('campuses.show');

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

        Route::get('/graduation', [StudentAcademicSummaryController::class, 'graduation'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.graduation');

        // Lifecycle tab: unified Student Actions + EGC Progression timeline (ADR-0009)
        Route::get('/lifecycle', [StudentAcademicSummaryController::class, 'lifecycle'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.lifecycle');

        // Backfill an authorizing Decision onto an existing transition (ADR-0008)
        Route::post('/lifecycle/attach-decision', [StudentAcademicSummaryController::class, 'attachDecision'])
            ->middleware('can:change_student_status')
            ->name('students.academic-summary.lifecycle.attach-decision');

        Route::get('/export', [StudentAcademicSummaryController::class, 'export'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.export');

        Route::get('/gold', [StudentAcademicSummaryController::class, 'gold'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.gold');

        Route::get('/fees', [StudentAcademicSummaryController::class, 'fees'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.fees');

        // Read-only Finance tab: fees + gold + scholarships summary via the
        // Finance read contract, deep-linking to the Finance Office (ADR-0007).
        Route::get('/finance', [StudentAcademicSummaryController::class, 'finance'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.finance');

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

        Route::get('/missing-decisions', [AcademicProgressionAuditController::class, 'missingDecisions'])
            ->middleware('can:view_student_action')
            ->name('missing-decisions');

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

        Route::get('/import', [StudentActionAuditController::class, 'importPage'])
            ->middleware('can:change_student_status')
            ->name('import');

        Route::get('/import/template', [StudentActionAuditController::class, 'downloadImportTemplate'])
            ->middleware('can:change_student_status')
            ->name('import.template');

        Route::post('/import/preview', [StudentActionAuditController::class, 'previewImport'])
            ->middleware('can:change_student_status')
            ->name('import.preview');

        Route::post('/import/execute', [StudentActionAuditController::class, 'executeImport'])
            ->middleware('can:change_student_status')
            ->name('import.execute');

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

    // Student Lifecycle Yearly Analysis
    Route::prefix('reports/student-lifecycle-yearly')->name('reports.student-lifecycle-yearly.')->group(function () {
        Route::get('/', [StudentLifecycleYearlyAnalysisController::class, 'index'])
            ->middleware('can:view_student_action')
            ->name('index');

        Route::get('/export', [StudentLifecycleYearlyAnalysisController::class, 'export'])
            ->middleware('can:view_student_action')
            ->name('export');
    });

    Route::prefix('reports/student-decisions')->name('reports.student-decisions.')->group(function () {
        Route::get('/', [StudentDecisionController::class, 'index'])
            ->middleware('can:view_student_action')
            ->name('index');

        Route::get('/{studentDecision}', [StudentDecisionController::class, 'show'])
            ->middleware('can:view_student_action')
            ->name('show');

        Route::post('/{studentDecision}/students/preview', [StudentDecisionController::class, 'previewStudents'])
            ->middleware('can:change_student_status')
            ->name('students.preview');

        Route::post('/{studentDecision}/students/bulk-link', [StudentDecisionController::class, 'bulkLinkStudents'])
            ->middleware('can:change_student_status')
            ->name('students.bulk-link');

        Route::post('/{studentDecision}/students/{actionLog}/unlink', [StudentDecisionController::class, 'unlinkStudent'])
            ->middleware('can:unlink_student_from_decision')
            ->name('students.unlink');

        Route::post('/', [StudentDecisionController::class, 'store'])
            ->middleware('can:change_student_status')
            ->name('store');

        Route::put('/{studentDecision}', [StudentDecisionController::class, 'update'])
            ->middleware('can:change_student_status')
            ->name('update');
    });

    /**
     * ==========================================================
     * Retake Course Registration (Đào tạo)
     * ==========================================================
     */
    Route::prefix('retake-course')->name('academic.retake-course.')->group(function () {
        Route::get('/', [RetakeCourseRegistrationController::class, 'index'])
            ->middleware('can:view_retake_course')
            ->name('index');

        Route::get('/create', [RetakeCourseRegistrationController::class, 'create'])
            ->middleware('can:create_retake_course')
            ->name('create');

        Route::post('/', [RetakeCourseRegistrationController::class, 'store'])
            ->middleware('can:create_retake_course')
            ->name('store');

        Route::post('/{registration}/sync', [RetakeCourseRegistrationController::class, 'sync'])
            ->middleware('can:create_retake_course')
            ->name('sync');

        Route::post('/{registration}/cancel', [RetakeCourseRegistrationController::class, 'cancel'])
            ->middleware('can:cancel_retake_course')
            ->name('cancel');
    });

    /**
     * ==========================================================
     * Exam Resit Operations (Thi lại — Đào tạo)
     * ==========================================================
     */
    Route::prefix('exam-resit')->name('academic.exam-resit.')->group(function () {
        Route::get('/', [ExamResitAttemptController::class, 'index'])
            ->middleware('can:view_exam_resit')
            ->name('index');

        Route::get('/create', [ExamResitAttemptController::class, 'create'])
            ->middleware('can:create_exam_resit')
            ->name('create');

        Route::post('/', [ExamResitAttemptController::class, 'store'])
            ->middleware('can:create_exam_resit')
            ->name('store');

        Route::get('/{examResit}/schedule', [ExamResitAttemptController::class, 'scheduleForm'])
            ->middleware('can:schedule_exam_resit')
            ->name('schedule.create');

        Route::post('/{examResit}/schedule', [ExamResitAttemptController::class, 'schedule'])
            ->middleware('can:schedule_exam_resit')
            ->name('schedule.store');

        Route::get('/{examResit}/complete', [ExamResitAttemptController::class, 'completeForm'])
            ->middleware('can:complete_exam_resit')
            ->name('complete.create');

        Route::post('/{examResit}/complete', [ExamResitAttemptController::class, 'complete'])
            ->middleware('can:complete_exam_resit')
            ->name('complete.store');

        Route::post('/{examResit}/cancel', [ExamResitAttemptController::class, 'cancel'])
            ->middleware('can:cancel_exam_resit')
            ->name('cancel');
    });

    /**
     * ==========================================================
     * Exam Schedule Authoring (room slots / sessions / invigilators)
     * ==========================================================
     */
    Route::prefix('exam-schedule')->name('academic.exam-schedule.')->group(function () {
        Route::get('/', [ExamScheduleController::class, 'index'])
            ->middleware('can:manage_exam_schedule')
            ->name('index');

        Route::post('/room-slots', [ExamScheduleController::class, 'storeRoomSlot'])
            ->middleware('can:manage_exam_schedule')
            ->name('room-slots.store');

        Route::post('/sessions', [ExamScheduleController::class, 'storeSession'])
            ->middleware('can:manage_exam_schedule')
            ->name('sessions.store');

        Route::post('/invigilators', [ExamScheduleController::class, 'assignInvigilator'])
            ->middleware('can:manage_exam_schedule')
            ->name('invigilators.store');
    });
});
