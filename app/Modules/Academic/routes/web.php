<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Constants\SemesterRoutes;
use App\Constants\StudentRoutes;
use App\Modules\Academic\Delivery\Http\Api\ClassSessionController as DeliveryClassSessionApiController;
use App\Modules\Academic\Http\Web\AcademicReportController;
use App\Modules\Academic\Http\Web\CourseRankingController;
use App\Modules\Academic\Http\Web\GpaHistoryController;
use App\Modules\Academic\Http\Web\GpaManagementController;
use App\Modules\Academic\Http\Web\PerformanceDashboardController;
use App\Modules\Academic\Http\Web\WarningCenterController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingRosterController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseRegistrationController;
use App\Modules\Academic\Delivery\Http\Web\AttendanceController as DeliveryAttendanceController;
use App\Modules\Academic\Delivery\Http\Web\AttendanceReportController as DeliveryAttendanceReportController;
use App\Modules\Academic\Delivery\Http\Web\BulkUpdateClassSessionAttendanceController;
use App\Modules\Academic\Delivery\Http\Web\BulkUpdateCourseOfferingSessionsController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\CanvasCourseController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\CanvasIntegrationController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\CanvasOAuthController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\CanvasSyllabusController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\CanvasSyncController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\PreviewCanvasGradeSyncController;
use App\Modules\Academic\Delivery\Http\Web\Canvas\SyncCanvasGradeController;
use App\Modules\Academic\Delivery\Http\Web\ClassSessionController as DeliveryClassSessionController;
use App\Modules\Academic\Delivery\Http\Web\CourseOfferingSplitController;
use App\Modules\Academic\Delivery\Http\Web\CourseOfferingStatisticsController;
use App\Modules\Academic\Delivery\Http\Web\CourseStatisticsController;
use App\Modules\Academic\Delivery\Http\Web\RecordClassSessionAttendanceController;
use App\Modules\Academic\Delivery\Http\Web\UnitStatisticsController;
use App\Modules\Academic\Http\Web\AcademicPlacementController;
use App\Modules\Academic\Http\Web\AcademicProgressionAuditController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingBulkDeletionController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingCatalogFormController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingCockpitController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingDeletionController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingDuplicationController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingInstructorAssignmentController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingRegistrationController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingRoomController;
use App\Modules\Academic\Http\Web\CampusDetailController;
use App\Modules\Academic\Http\Web\ExamResitAttemptController;
use App\Modules\Academic\Http\Web\ExamScheduleController;
use App\Modules\Academic\Http\Web\FinalizeCourseOfferingController;
use App\Modules\Academic\Http\Web\RecalculateApplyController;
use App\Modules\Academic\Http\Web\RecalculatePreviewController;
use App\Modules\Academic\Http\Web\RetakeCourseRegistrationController;
use App\Modules\Academic\Http\Web\StudentAcademicSummaryController;
use App\Modules\Academic\Http\Web\StudentActionAuditController;
use App\Modules\Academic\Http\Web\StudentActionController;
use App\Modules\Academic\Http\Web\StudentController;
use App\Modules\Academic\Http\Web\StudentDecisionController;
use App\Modules\Academic\Http\Web\StudentLifecycleYearlyAnalysisController;
use App\Modules\Academic\Http\Web\StudentStatusController;
use App\Modules\Academic\Progression\Http\Web\SemesterEnrollmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/../Catalog/routes/web.php';
require __DIR__.'/../FacultyWorkforce/routes/web.php';

Route::model('courseOffering', implode('\\', ['App', 'Models', 'CourseOffering']));

/*
|--------------------------------------------------------------------------
| Student Management Routes
|--------------------------------------------------------------------------
|
| Routes for advanced student management features including academic records,
| program changes, course retakes, academic standing, and status tracking.
|
*/

Route::middleware(['auth', 'verified', 'campus.selected'])->group(function () {
    Route::prefix('course-registrations')->name('course-registrations.')->group(function () {
        Route::get('/', [CourseRegistrationController::class, 'index'])
            ->middleware('can:view_course_registration')
            ->name('index');
        Route::get('/create', [CourseRegistrationController::class, 'create'])
            ->middleware('can:create_course_registration')
            ->name('create');
        Route::post('/', [CourseRegistrationController::class, 'store'])
            ->middleware('can:create_course_registration')
            ->name('store');
        Route::get('/{adminCourseRegistration}', [CourseRegistrationController::class, 'show'])
            ->middleware('can:view_course_registration')
            ->name('show');
        Route::get('/{adminCourseRegistration}/edit', [CourseRegistrationController::class, 'edit'])
            ->middleware('can:edit_course_registration')
            ->name('edit');
        Route::put('/{adminCourseRegistration}', [CourseRegistrationController::class, 'update'])
            ->middleware('can:edit_course_registration')
            ->name('update');
        Route::delete('/{adminCourseRegistration}', [CourseRegistrationController::class, 'destroy'])
            ->middleware('can:delete_course_registration')
            ->name('destroy');
        Route::patch('/{adminCourseRegistration}/drop', [CourseRegistrationController::class, 'drop'])
            ->middleware(['can:edit_course_registration', 'can:manage_course_registration'])
            ->name('drop');
        Route::patch('/{adminCourseRegistration}/withdraw', [CourseRegistrationController::class, 'withdraw'])
            ->middleware(['can:edit_course_registration', 'can:manage_course_registration'])
            ->name('withdraw');
    });

    Route::prefix('api/course-registrations')->name('api.course-registrations.')->group(function () {
        Route::delete('/bulk-delete', [CourseRegistrationController::class, 'bulkDestroy'])
            ->middleware('can:delete_course_registration')
            ->name('bulk-delete');
        Route::get('/available-courses', [CourseRegistrationController::class, 'getAvailableCourses'])
            ->middleware('can:view_course_registration')
            ->name('available-courses');
        Route::get('/available-units', [CourseRegistrationController::class, 'getAvailableUnits'])
            ->middleware('can:view_course_registration')
            ->name('available-units');
        Route::get('/check-eligibility', [CourseRegistrationController::class, 'checkEligibility'])
            ->middleware('can:view_course_registration')
            ->name('check-eligibility');
        Route::get('/student-registrations', [CourseRegistrationController::class, 'getStudentRegistrations'])
            ->middleware('can:view_course_registration')
            ->name('student-registrations');
    });

    Route::get('attendance', [DeliveryAttendanceController::class, 'index'])
        ->middleware('can:view_course_offering')
        ->name('attendance.index');

    Route::get('course-statistics/{courseOffering}/students', [DeliveryAttendanceReportController::class, 'show'])
        ->middleware(['campus.selected', 'can:view_attendance'])
        ->name('course-statistics.show');

    Route::get('course-statistics/{courseOffering}/export', [DeliveryAttendanceReportController::class, 'export'])
        ->middleware(['campus.selected', 'can:view_attendance'])
        ->name('course-statistics.export');

    Route::get('course-statistics', [CourseStatisticsController::class, 'index'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.index');

    Route::get('course-statistics/units/{unitId}', [UnitStatisticsController::class, 'show'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.units.show');

    Route::get('course-statistics/{courseOffering}/assessment-scores', [CourseStatisticsController::class, 'assessmentScores'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.assessment-scores');

    Route::get('course-statistics/{courseOffering}/export-combined', [CourseStatisticsController::class, 'exportCombined'])
        ->middleware('can:view_attendance')
        ->name('course-statistics.export-combined');

    Route::get('class-sessions', [DeliveryClassSessionController::class, 'index'])
        ->middleware('can:view_class_session')
        ->name('class-sessions.index');

    Route::get('class-sessions/create', [DeliveryClassSessionController::class, 'create'])
        ->middleware('can:create_class_session')
        ->name('class-sessions.create');

    Route::get('class-sessions/{classSession}/edit', [DeliveryClassSessionController::class, 'edit'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.edit');

    Route::post('class-sessions', [DeliveryClassSessionController::class, 'store'])
        ->middleware('can:create_class_session')
        ->name('class-sessions.store');

    Route::get('class-sessions/{classSession}', [DeliveryClassSessionController::class, 'show'])
        ->middleware('can:view_class_session')
        ->name('class-sessions.show');

    Route::put('class-sessions/{classSession}', [DeliveryClassSessionController::class, 'update'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.update');

    Route::delete('class-sessions/{classSession}', [DeliveryClassSessionController::class, 'destroy'])
        ->middleware('can:delete_class_session')
        ->name('class-sessions.destroy');

    Route::delete('class-sessions', [DeliveryClassSessionController::class, 'bulkDestroy'])
        ->middleware('can:delete_class_session')
        ->name('class-sessions.bulk-destroy');

    Route::post('course-offerings/{courseOffering}/class-sessions/generate', [DeliveryClassSessionController::class, 'generate'])
        ->middleware('can:generate_class_session')
        ->name('class-sessions.generate');

    Route::get('course-offerings/{courseOffering}/class-sessions/add', [DeliveryClassSessionController::class, 'createForOffering'])
        ->middleware('can:create_class_session')
        ->name('class-sessions.add-for-offering');

    Route::get('class-sessions/{classSession}/quick-edit', [DeliveryClassSessionController::class, 'editModal'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.quick-edit');

    Route::get('course-offerings/{courseOffering}/class-sessions/bulk-edit', [DeliveryClassSessionController::class, 'bulkEditModal'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.bulk-edit');

    Route::post('class-sessions/{classSession}/generate-attendance', [DeliveryClassSessionController::class, 'generateAttendance'])
        ->middleware('can:generate_class_session_attendance')
        ->name('class-sessions.generate-attendance');

    Route::post('class-sessions/{classSession}/attendance/bulk-update', BulkUpdateClassSessionAttendanceController::class)
        ->middleware('can:edit_class_session')
        ->name('class-sessions.attendance.bulk-update');

    Route::post('course-offerings/{courseOffering}/class-sessions/{classSession}/record-attendance', RecordClassSessionAttendanceController::class)
        ->middleware('can:create_course_offering')
        ->name(CourseOfferingRoutes::RECORD_SESSION_ATTENDANCE);

    Route::get('class-sessions/{classSession}/export-attendance', [DeliveryClassSessionController::class, 'exportAttendance'])
        ->middleware('can:export_class_session_attendance')
        ->name('class-sessions.export-attendance');

    Route::prefix('api/course-offerings/{courseOffering}/class-sessions')->group(function () {
        Route::get('/', [DeliveryClassSessionApiController::class, 'index'])
            ->middleware('can:view_course_offering');
        Route::post('/generate', [DeliveryClassSessionApiController::class, 'generate'])
            ->middleware('can:edit_course_offering');
        Route::delete('/', [DeliveryClassSessionApiController::class, 'destroy'])
            ->middleware('can:edit_course_offering');
    });

    Route::post('api/class-sessions', [DeliveryClassSessionApiController::class, 'store'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.store');

    Route::delete('api/class-sessions/bulk', [DeliveryClassSessionApiController::class, 'bulkDestroy'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.bulk-destroy');

    Route::delete('api/class-sessions/{classSession}', [DeliveryClassSessionApiController::class, 'destroySingle'])
        ->middleware('can:edit_course_offering')
        ->name('api.class-sessions.destroy');

    Route::post('api/course-offerings/{courseOffering}/class-sessions/bulk-update', BulkUpdateCourseOfferingSessionsController::class)
        ->middleware('can:edit_course_offering')
        ->name('course-offerings.bulk-update-class-sessions');

    Route::prefix('course-offerings')->group(function () {
        Route::get('/', [CourseOfferingCockpitController::class, 'index'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::INDEX);

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

        Route::get('/{courseOffering}', [CourseOfferingCockpitController::class, 'show'])
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::SHOW);

        Route::delete('/{courseOffering}', CourseOfferingDeletionController::class)
            ->middleware('can:delete_course_offering')
            ->name(CourseOfferingRoutes::DESTROY);

        Route::post('/{courseOffering}/duplicate', CourseOfferingDuplicationController::class)
            ->middleware('can:create_course_offering')
            ->name(CourseOfferingRoutes::DUPLICATE);

        Route::post('/{courseOffering}/delete-student-registration', [CourseOfferingRosterController::class, 'removeStudent'])
            ->middleware('can:delete_student_registration')
            ->name('course-offerings.delete-student-registration');

        Route::post('/{courseOffering}/move-student', [CourseOfferingRosterController::class, 'moveStudent'])
            ->middleware('can:edit_course_offering')
            ->name('course-offerings.move-student');

        Route::get('/{courseOffering}/split', [CourseOfferingSplitController::class, 'show'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::SPLIT_SHOW);

        Route::post('/{courseOffering}/split', [CourseOfferingSplitController::class, 'perform'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::SPLIT_PERFORM);
    });

    Route::prefix('api/course-offerings')->group(function () {
        Route::get('/statistics', CourseOfferingStatisticsController::class)
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::API_STATISTICS);

        Route::post('/{courseOffering}/search-students', [CourseOfferingRegistrationController::class, 'search'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_SEARCH_STUDENTS);

        Route::post('/{courseOffering}/bulk-register-students', [CourseOfferingRegistrationController::class, 'bulkRegister'])
            ->middleware('can:add_student_registration')
            ->name(CourseOfferingRoutes::API_BULK_REGISTER_STUDENTS);

        Route::get('/check-instructor-assignments', CourseOfferingInstructorAssignmentController::class)
            ->middleware('can:view_course_offering')
            ->name(CourseOfferingRoutes::API_CHECK_INSTRUCTOR_ASSIGNMENTS);
        Route::delete('/bulk-delete', CourseOfferingBulkDeletionController::class)
            ->middleware('can:delete_course_offering')
            ->name(CourseOfferingRoutes::API_BULK_DELETE);

        Route::post('/{courseOffering}/bulk-update-status', [CourseOfferingRosterController::class, 'bulkUpdateStatus'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_BULK_UPDATE_STATUS);

        Route::post('/bulk-assign-lectures', [CourseOfferingRosterController::class, 'bulkAssignInstructors'])
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_BULK_ASSIGN_LECTURES);

        Route::post('/{courseOffering}/change-room', CourseOfferingRoomController::class)
            ->middleware('can:edit_course_offering')
            ->name(CourseOfferingRoutes::API_CHANGE_ROOM);
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

        // Lifecycle tab: unified Student Actions + EGC Progression timeline (ADR-0049)
        Route::get('/lifecycle', [StudentAcademicSummaryController::class, 'lifecycle'])
            ->middleware('can:view_student_summary')
            ->name('students.academic-summary.lifecycle');

        // Backfill an authorizing Decision onto an existing transition (ADR-0048)
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('course-offerings')->group(function () {
        Route::post('/{courseOffering}/finalize', FinalizeCourseOfferingController::class)
            ->middleware('can:complete_course_offering')
            ->name(CourseOfferingRoutes::FINALIZE);
    });

    Route::prefix('api/course-offerings')->group(function () {
        Route::post('/{courseOffering}/sync-grades/preview', PreviewCanvasGradeSyncController::class)
            ->middleware('can:sync_course_grades')
            ->name(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW);

        Route::post('/{courseOffering}/sync-grades', SyncCanvasGradeController::class)
            ->middleware('can:sync_course_grades')
            ->name(CourseOfferingRoutes::API_SYNC_GRADES_APPLY);

        Route::post('/{courseOffering}/recalculate/preview', RecalculatePreviewController::class)
            ->middleware('can:recalculate_course_offering')
            ->name(CourseOfferingRoutes::API_RECALCULATE_PREVIEW);

        Route::post('/{courseOffering}/recalculate/apply', RecalculateApplyController::class)
            ->middleware('can:recalculate_course_offering')
            ->name(CourseOfferingRoutes::API_RECALCULATE_APPLY);
    });
});

Route::middleware('auth')->group(function () {
    Route::get('semesters/{semester}/enrollment', [SemesterEnrollmentController::class, 'show'])
        ->whereNumber('semester')
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::ENROLLMENT_SHOW);

    Route::post('api/semesters/{semester}/enrollment/generate', [SemesterEnrollmentController::class, 'generateEnrollments'])
        ->whereNumber('semester')
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_ENROLLMENT_GENERATE);
});

Route::middleware(['auth', 'verified'])->name('schedules.')->group(function () {
    Route::get('/schedule-management', function () {
        return Inertia::render('ClassSchedule/Index');
    })->name('index');
});

Route::middleware(['auth', 'verified'])->prefix('academic')->name('academic.')->group(function () {
    Route::prefix('gpa')->name('gpa.')->group(function () {
        Route::get('/finalize', [GpaManagementController::class, 'index'])
            ->middleware('can:view_gpa_finalization')
            ->name('finalize.index');
        Route::post('/finalize', [GpaManagementController::class, 'finalize'])
            ->middleware('can:create_gpa_finalization')
            ->name('finalize.store');

        // History & Export
        Route::get('/history', [GpaHistoryController::class, 'index'])
            ->middleware('can:view_gpa_history')
            ->name('history');
        Route::get('/history/export', [GpaHistoryController::class, 'export'])
            ->middleware('can:view_gpa_history')
            ->name('history.export');
    });

    // Performance Dashboard
    Route::get('/students/performance', [PerformanceDashboardController::class, 'index'])
        ->middleware('can:view_performance_dashboard')
        ->name('students.performance');

    Route::prefix('warnings')->name('warnings.')->group(function () {
        Route::get('/', [WarningCenterController::class, 'index'])
            ->name('index');
        Route::get('/settings', [WarningCenterController::class, 'settings'])
            ->name('settings');
        Route::put('/settings', [WarningCenterController::class, 'updateSettings'])
            ->name('settings.update');
        Route::post('/academic-standing/{gpaCalculation}/send', [WarningCenterController::class, 'sendAcademicStanding'])
            ->name('academic-standing.send');
        Route::post('/attendance/{courseOffering}/{student}/send', [WarningCenterController::class, 'sendAttendance'])
            ->name('attendance.send');
    });

    Route::get('/report', [AcademicReportController::class, 'index'])
        ->middleware('can:view_academic_report')
        ->name('report.index');

    Route::get('/course-ranking', [CourseRankingController::class, 'index'])
        ->middleware('can:view_academic_report')
        ->name('course-ranking.index');
});

Route::prefix('admin/canvas')
    ->middleware(['auth', 'verified', 'campus.selected'])
    ->name('admin.canvas.')
    ->group(function () {
        // Canvas Integrations
        Route::get('/integrations', [CanvasIntegrationController::class, 'index'])
            ->middleware('can:view_canvas_integration')
            ->name('integrations.index');

        Route::post('/integrations', [CanvasIntegrationController::class, 'store'])
            ->middleware('can:create_canvas_integration')
            ->name('integrations.store');

        Route::delete('/integrations/{integration}', [CanvasIntegrationController::class, 'destroy'])
            ->middleware('can:delete_canvas_integration')
            ->name('integrations.destroy');

        Route::post('/integrations/{integration}/toggle', [CanvasIntegrationController::class, 'toggleActive'])
            ->middleware('can:edit_canvas_integration')
            ->name('integrations.toggle');

        // OAuth Flow
        Route::get('/oauth/redirect/{integration}', [CanvasOAuthController::class, 'redirect'])
            ->name('oauth.redirect');

        Route::get('/oauth/callback', [CanvasOAuthController::class, 'callback'])
            ->name('oauth.callback');

        Route::post('/oauth/revoke/{integration}', [CanvasOAuthController::class, 'revoke'])
            ->name('oauth.revoke');

        // Sync
        Route::post('/sync/{integration}', [CanvasSyncController::class, 'syncCourses'])
            ->name('sync.courses');

        // Canvas Courses
        Route::get('/courses', [CanvasCourseController::class, 'index'])
            ->name('courses.index');

        Route::post('/courses/map', [CanvasCourseController::class, 'mapCourse'])
            ->name('courses.map');

        Route::post('/courses/{mapping}/unmap', [CanvasCourseController::class, 'unmapCourse'])
            ->name('courses.unmap');

        Route::post('/courses/{mapping}/ignore', [CanvasCourseController::class, 'ignoreCourse'])
            ->name('courses.ignore');

        // API endpoint for course offerings dropdown
        Route::get('/api/course-offerings', [CanvasCourseController::class, 'getAvailableCourseOfferings'])
            ->name('api.course-offerings');

        // Syllabus sync
        Route::get('/courses/{mapping}/sync-summary', [CanvasSyllabusController::class, 'getSyncSummary'])
            ->name('courses.sync-summary');

        // Assignment sync
        Route::get('/courses/{mapping}/assignments/sync-summary', [CanvasSyllabusController::class, 'getAssignmentSyncSummary'])
            ->name('courses.assignments.sync-summary');
        Route::post('/courses/{mapping}/sync-assignments', [CanvasSyllabusController::class, 'syncAssignments'])
            ->name('courses.sync-assignments');

        // Grade sync
        Route::get('/courses/{mapping}/grades/sync-summary', [CanvasSyllabusController::class, 'getGradeSyncSummary'])
            ->name('courses.grades.sync-summary');
        Route::post('/courses/{mapping}/sync-grades', [CanvasSyllabusController::class, 'syncGrades'])
            ->name('courses.sync-grades');
    });
