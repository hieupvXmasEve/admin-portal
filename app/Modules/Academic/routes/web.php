<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Modules\Academic\Http\Web\AcademicPlacementController;
use App\Modules\Academic\Http\Web\AcademicProgressionAuditController;
use App\Modules\Academic\Http\Web\BuildingController;
use App\Modules\Academic\Http\Web\CampusController;
use App\Modules\Academic\Http\Web\RetakeCourseRegistrationController;
use App\Modules\Academic\Http\Web\StudentAcademicSummaryController;
use App\Modules\Academic\Http\Web\StudentActionAuditController;
use App\Modules\Academic\Http\Web\StudentActionController;
use App\Modules\Academic\Http\Web\StudentController;
use App\Modules\Academic\Http\Web\StudentDecisionController;
use App\Modules\Academic\Http\Web\StudentLifecycleYearlyAnalysisController;
use App\Modules\Academic\Http\Web\StudentStatusController;
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
     * Campuses (CRU — no delete)
     * ==========================================================
     */
    Route::prefix('campuses')->name('campuses.')->group(function () {
        Route::get('/', [CampusController::class, 'index'])
            ->middleware('can:view_campus')
            ->name('index');

        Route::get('/create', [CampusController::class, 'create'])
            ->middleware('can:create_campus')
            ->name('create');

        Route::post('/', [CampusController::class, 'store'])
            ->middleware('can:create_campus')
            ->name('store');

        Route::get('/{campus}', [CampusController::class, 'show'])
            ->middleware('can:view_campus')
            ->name('show');

        Route::get('/{campus}/edit', [CampusController::class, 'edit'])
            ->middleware('can:edit_campus')
            ->name('edit');

        Route::put('/{campus}', [CampusController::class, 'update'])
            ->middleware('can:edit_campus')
            ->name('update');
    });

    /**
     * ==========================================================
     * Buildings
     * ==========================================================
     */
    Route::prefix('buildings')->name('buildings.')->group(function () {
        Route::get('/', [BuildingController::class, 'index'])
            ->middleware('can:view_building')
            ->name('index');

        Route::get('/api', [BuildingController::class, 'api'])
            ->name('api');

        Route::get('/{building}', [BuildingController::class, 'show'])
            ->middleware('can:view_building')
            ->name('show');
    });

    /**
     * Campus-scoped building CRUD routes (route-based modals via @inertiaui/modal-vue).
     * create/edit render an Inertia page that wraps its content in <Modal>.
     * store/update/destroy perform the mutation and redirect back to campuses.show.
     */
    Route::prefix('campuses/{campus}/buildings')->name('campuses.buildings.')->group(function () {
        Route::get('/create', [BuildingController::class, 'create'])
            ->middleware('can:create_building')
            ->name('create');

        Route::post('/', [BuildingController::class, 'store'])
            ->middleware('can:create_building')
            ->name('store');

        Route::get('/{building}/edit', [BuildingController::class, 'edit'])
            ->middleware('can:edit_building')
            ->name('edit');

        Route::put('/{building}', [BuildingController::class, 'update'])
            ->middleware('can:edit_building')
            ->name('update');

        Route::delete('/{building}', [BuildingController::class, 'destroy'])
            ->middleware('can:delete_building')
            ->name('destroy');
    });

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
});
