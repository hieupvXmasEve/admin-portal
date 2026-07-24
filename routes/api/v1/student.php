<?php

declare(strict_types=1);

use App\Http\Controllers\Api\GoldTransactionController;
use App\Http\Controllers\Api\StudentWalletController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Student\AcademicRecordController;
use App\Http\Controllers\Api\V1\Student\CalendarController;
use App\Http\Controllers\Api\V1\Student\CourseRegistrationController;
use App\Http\Controllers\Api\V1\Student\CurriculumController;
use App\Http\Controllers\Api\V1\Student\DashboardController;
use App\Http\Controllers\Api\V1\Student\FinanceController;
use App\Http\Controllers\Api\V1\Student\GradeController;
use App\Http\Controllers\Api\V1\Student\ModuleController;
use App\Http\Controllers\Api\V1\Student\NotificationController;
use App\Http\Controllers\Api\V1\Student\ProfileController;
use App\Http\Controllers\Api\V1\Student\TimetableController;
use App\Modules\Academic\Delivery\Http\Api\Student\AttendanceController;
use App\Modules\Finance\Http\Api\Student\StudentFinanceController;
use App\Modules\Upload\Http\Api\UploadController;
use Illuminate\Support\Facades\Route;

// Protected student API routes
Route::middleware([
    'auth:sanctum',
    'api.logging',
    'api.actor:student_or_parent',
])->group(function () {

    // Sub-group with either middleware requirement
    Route::middleware([
        'either:parent.student.access,student.api.auth',
        //    'student.api.rate:student-api',
    ])->group(function () {

        // Dashboard endpoints
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])
                //            ->middleware(['student.api.rate:student-dashboard'])
                ->name('index');

            Route::get('/gpa', [DashboardController::class, 'gpa'])->name('gpa');
            Route::get('/credit-progress', [DashboardController::class, 'creditProgress'])->name('credit-progress');
            Route::get('/academic-holds', [DashboardController::class, 'academicHolds'])->name('academic-holds');
            Route::get('/upcoming-assessments', [DashboardController::class, 'upcomingAssessments'])->name('upcoming-assessments');
        });

        // Course registration endpoints
        Route::prefix('course')->name('course-registration.')->group(function () {
            // Get courses student is currently enrolled in
            Route::get('/enrolled', [CourseRegistrationController::class, 'enrolledCourses'])
                ->name('enrolled-courses');

            // Get courses available for registration (not yet enrolled)
            // Route::get('/available-for-registration', [CourseRegistrationController::class, 'availableForRegistration'])
            //     ->name('available-for-registration');

            // Keep backward compatibility for the current URL
            Route::get('/available', [CourseRegistrationController::class, 'enrolledCourses'])
                ->name('available-courses');

            // Course detail - full schedule and grades for enrolled course
            // Route::get('/enrolled/{courseOfferingId}', [CourseRegistrationController::class, 'courseDetail'])
            //     ->name('course-detail');

            // Keep backward compatibility
            Route::get('/available/{courseOfferingId}', [CourseRegistrationController::class, 'courseDetail'])
                ->name('course-detail-legacy');

            // TODO: complete this endpoint
            //            Route::post('/register', [CourseRegistrationController::class, 'register'])
            //                ->middleware(['student.api.rate:student-registration'])
            //                ->name('register');

            //            Route::delete('/drop/{registration}', [CourseRegistrationController::class, 'drop'])
            //                ->middleware(['student.api.rate:student-registration'])
            //                ->name('drop');

            // TODO: complete this endpoint
            //            Route::get('/my-registrations', [CourseRegistrationController::class, 'myRegistrations'])
            //                ->name('my-registrations');

            //            Route::post('/validate-registration', [CourseRegistrationController::class, 'validateRegistration'])
            //                ->name('validate-registration');

            //            Route::get('/schedule-conflicts/{courseOfferingId}', [CourseRegistrationController::class, 'checkScheduleConflicts'])
            //                ->name('schedule-conflicts');
        });

        // Timetable endpoints
        Route::prefix('timetable')->name('timetable.')->group(function () {
            Route::get('/', [TimetableController::class, 'index'])->name('index');
            // Route::get('/weekly', [TimetableController::class, 'weekly'])->name('weekly');
            // Route::get('/class-session/{classSession}', [TimetableController::class, 'classSessionDetail'])
            //     ->name('class-session-detail');
            // Route::get('/filter-options', [TimetableController::class, 'filterOptions'])->name('filter-options');
        });

        // Grades and academic progress endpoints
        Route::prefix('grades')->name('grades.')->group(function () {
            Route::get('/', [GradeController::class, 'index'])->name('index');
            Route::get('/gpa-trend', [GradeController::class, 'gpaTrend'])->name('gpa-trend');
            Route::get('/assessments', [GradeController::class, 'assessments'])->name('assessments');
            Route::get('/assessment/{assessmentId}', [GradeController::class, 'assessmentDetail'])
                ->name('assessment-detail');
        });

        // Academic Records & GPA Summary
        Route::get('/academic-records', [AcademicRecordController::class, 'index'])->name('academic-records.index');

        // Module progress endpoints (Finland campus modular system)
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/', [ModuleController::class, 'index'])->name('index');
            Route::get('/dashboard', [ModuleController::class, 'dashboard'])->name('dashboard');
            Route::get('/roadmap', [ModuleController::class, 'roadmap'])->name('roadmap');
            Route::get('/{module}', [ModuleController::class, 'show'])->name('show');
        });

        // Attendance endpoints
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/report', [AttendanceController::class, 'report'])->name('report');
            //        Route::get('/summary', [AttendanceController::class, 'summary'])->name('summary');
            //        Route::get('/alerts', [AttendanceController::class, 'alerts'])->name('alerts');
            Route::get('/course/{courseOfferingId}', [AttendanceController::class, 'courseAttendance'])
                ->name('course-attendance');
        });

        // Profile management endpoints
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])
                ->middleware(['student.api.rate:student-profile'])
                ->name('show');

            Route::put('/', [ProfileController::class, 'update'])
                ->middleware(['student.api.rate:student-profile'])
                ->name('update');

            Route::post('/avatar', [ProfileController::class, 'uploadAvatar'])
                ->middleware(['student.api.rate:student-profile'])
                ->name('upload-avatar');

            Route::get('/study-plan', [ProfileController::class, 'studyPlan'])->name('study-plan');
            Route::get('/academic-history', [ProfileController::class, 'academicHistory'])->name('academic-history');
        });

        // Curriculum and program tracking endpoints
        Route::prefix('curriculum')->name('curriculum.')->group(function () {
            Route::get('/', [CurriculumController::class, 'index'])->name('index');
            Route::get('/by-semester', [CurriculumController::class, 'bySemester'])->name('by-semester');
            //            Route::get('/prerequisite-tree', [CurriculumController::class, 'prerequisiteTree'])->name('prerequisite-tree');
            Route::get('/program-requirements', [CurriculumController::class, 'programRequirements'])->name('program-requirements');
            Route::get('/roadmap', [CurriculumController::class, 'roadmap'])->name('roadmap');
        });

        // Notification endpoints
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/summary', [NotificationController::class, 'summary'])->name('summary');
            Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
            Route::post('/mark-multiple-read', [NotificationController::class, 'markMultipleAsRead'])->name('mark-multiple-read');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');

            // Notification preferences
            Route::prefix('preferences')->name('preferences.')->group(function () {
                Route::get('/events', [NotificationPreferenceController::class, 'getEventPreferences'])->name('events.get');
                Route::put('/events', [NotificationPreferenceController::class, 'updateEventPreferences'])->name('events.update');
            });
        });

        // Academic calendar endpoints
        Route::prefix('calendar')->name('calendar.')->group(function () {
            Route::get('/semesters', [CalendarController::class, 'semesters'])->name('semesters');
            Route::get('/semester/{semester}/deadlines', [CalendarController::class, 'semesterDeadlines'])->name('semester-deadlines');
            Route::get('/academic-calendar', [CalendarController::class, 'academicCalendar'])->name('academic-calendar');
            Route::get('/current-semester', [CalendarController::class, 'currentSemester'])->name('current-semester');
        });

        // Student-controlled uploads
        Route::prefix('uploads')->name('uploads.')->group(function () {
            Route::post('/', [UploadController::class, 'upload'])
                // ->middleware(['throttle:uploads-single'])
                ->name('store');
        });

        // Gold Wallet endpoints
        Route::prefix('gold-wallet')->name('wallet.')->group(function () {
            // Current student wallet
            Route::get('/', [StudentWalletController::class, 'show'])->name('show');
            Route::get('/summary', [StudentWalletController::class, 'summary'])->name('summary');

            // Transaction endpoints
            Route::prefix('transactions')->name('transactions.')->group(function () {
                Route::get('/', [GoldTransactionController::class, 'index'])->name('index');
                Route::get('/recent', [GoldTransactionController::class, 'recent'])->name('recent');
                Route::get('/stats', [GoldTransactionController::class, 'stats'])->name('stats');
                Route::get('/{transaction}', [GoldTransactionController::class, 'show'])->name('show');
            });
        });

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [FinanceController::class, 'index'])->name('index');
            Route::get('/balance', [StudentFinanceController::class, 'balance'])->name('balance');
            Route::get('/charges', [StudentFinanceController::class, 'charges'])->name('charges');
            Route::get('/charges/{chargeId}', [StudentFinanceController::class, 'chargeDetail'])->name('charges.show');
            Route::get('/payments', [StudentFinanceController::class, 'payments'])->name('payments');
            Route::get('/payments/{paymentId}', [StudentFinanceController::class, 'paymentDetail'])->name('payments.show');
            Route::get('/dng-requests', [StudentFinanceController::class, 'dngRequests'])->name('dng-requests');
            Route::get('/dng-requests/all', [StudentFinanceController::class, 'dngRequestsAll'])->name('dng-requests.all');
            Route::get('/dng-requests/{dngRequestId}', [StudentFinanceController::class, 'dngRequestDetail'])->name('dng-requests.show');
            Route::post('/dng-requests/{dngRequestId}/qr', [StudentFinanceController::class, 'dngRequestQr'])->name('dng-requests.qr');
            Route::post('/dng-requests/{dngRequestId}/installment', [StudentFinanceController::class, 'dngRequestInstallment'])->name('dng-requests.installment');
            Route::post('/dng/qr', [StudentFinanceController::class, 'dngQr'])->name('dng.qr');
            Route::post('/dng/installment', [StudentFinanceController::class, 'dngInstallment'])->name('dng.installment');
            Route::get('/invoices', [StudentFinanceController::class, 'invoices'])->name('invoices');
            Route::get('/invoices/{invoiceId}', [StudentFinanceController::class, 'invoiceDetail'])->name('invoices.show');
            Route::get('/overview', [StudentFinanceController::class, 'overview'])->name('overview');
            Route::get('/{semester}', [FinanceController::class, 'semester'])->name('semester');
        });

    }); // End either middleware group

}); // End auth:sanctum group
