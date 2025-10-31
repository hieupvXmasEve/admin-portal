<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Student\AttendanceController;
use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Controllers\Api\V1\Student\CalendarController;
use App\Http\Controllers\Api\V1\Student\ClubController;
use App\Http\Controllers\Api\V1\Student\ClubManagementController;
use App\Http\Controllers\Api\V1\Student\CourseRegistrationController;
use App\Http\Controllers\Api\V1\Student\CurriculumController;
use App\Http\Controllers\Api\V1\Student\DashboardController;
use App\Http\Controllers\Api\V1\Student\EventController;
use App\Http\Controllers\Api\V1\Student\GradeController;
use App\Http\Controllers\Api\V1\Student\ModuleController;
use App\Http\Controllers\Api\V1\Student\QueryTicketController;
use App\Http\Controllers\Api\V1\Student\NotificationController;
use App\Http\Controllers\Api\V1\Student\ProfileController;
use App\Http\Controllers\Api\V1\Student\TimetableController;
use App\Http\Controllers\Api\V1\Student\CashWalletController;
use App\Http\Controllers\Api\StudentWalletController;
use App\Http\Controllers\Api\GoldTransactionController;
use App\Http\Controllers\Api\ImageUploadController;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        // ->middleware(['student.api.rate:student-auth'])
        ->name('login');

    Route::post('/login/google', [AuthController::class, 'loginWithGoogle'])
        // ->middleware(['student.api.rate:student-auth'])
        ->name('login.google');

    Route::post('/refresh', [AuthController::class, 'refresh'])
        // ->middleware(['student.api.rate:student-auth'])
        ->name('refresh');
});

// Protected student API routes
Route::middleware([
    'auth:sanctum',
    'api.logging',
])->group(function () {

    // Sub-group with either middleware requirement
    Route::middleware([
        'either:parent.student.access,student.api.auth',
        //    'student.api.rate:student-api',
    ])->group(function () {

        // Authentication management
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });

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
            Route::get('/available-for-registration', [CourseRegistrationController::class, 'availableForRegistration'])
                ->name('available-for-registration');

            // Keep backward compatibility for the current URL
            Route::get('/available', [CourseRegistrationController::class, 'enrolledCourses'])
                ->name('available-courses');

            // Course detail - full schedule and grades for enrolled course
            Route::get('/enrolled/{courseOfferingId}', [CourseRegistrationController::class, 'courseDetail'])
                ->name('course-detail');

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
            Route::get('/weekly', [TimetableController::class, 'weekly'])->name('weekly');
            Route::get('/class-session/{classSession}', [TimetableController::class, 'classSessionDetail'])
                ->name('class-session-detail');
            Route::get('/filter-options', [TimetableController::class, 'filterOptions'])->name('filter-options');
        });

        // Grades and academic progress endpoints
        Route::prefix('grades')->name('grades.')->group(function () {
            Route::get('/', [GradeController::class, 'index'])->name('index');
            Route::get('/gpa-trend', [GradeController::class, 'gpaTrend'])->name('gpa-trend');
            Route::get('/course/{courseOfferingId}', [GradeController::class, 'courseGrades'])
                ->name('course-grades');
            Route::get('/assessments', [GradeController::class, 'assessments'])->name('assessments');
            Route::get('/assessment/{assessmentId}', [GradeController::class, 'assessmentDetail'])
                ->name('assessment-detail');
        });

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
                Route::get('/events', [\App\Http\Controllers\Api\V1\NotificationPreferenceController::class, 'getEventPreferences'])->name('events.get');
                Route::put('/events', [\App\Http\Controllers\Api\V1\NotificationPreferenceController::class, 'updateEventPreferences'])->name('events.update');
            });
        });

        // Academic calendar endpoints
        Route::prefix('calendar')->name('calendar.')->group(function () {
            Route::get('/semesters', [CalendarController::class, 'semesters'])->name('semesters');
            Route::get('/semester/{semester}/deadlines', [CalendarController::class, 'semesterDeadlines'])->name('semester-deadlines');
            Route::get('/academic-calendar', [CalendarController::class, 'academicCalendar'])->name('academic-calendar');
            Route::get('/current-semester', [CalendarController::class, 'currentSemester'])->name('current-semester');
        });

        // Form endpoints
        Route::prefix('forms')->name('forms.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Student\FormController::class, 'index'])->name('index');
            Route::get('/{form}', [\App\Http\Controllers\Api\V1\Student\FormController::class, 'show'])->name('show');
            Route::post('/{form}/submit', [\App\Http\Controllers\Api\V1\Student\FormController::class, 'submit'])->name('submit');
        });

        // Query ticket endpoints
        Route::prefix('queries')->name('queries.')->group(function () {
            Route::get('/', [QueryTicketController::class, 'index'])->name('index');
            Route::get('/{ticket}', [QueryTicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/replies', [QueryTicketController::class, 'storeReply'])->name('replies.store');
        });

        // Student-controlled uploads
        Route::prefix('uploads')->name('uploads.')->group(function () {
            Route::post('/', [ImageUploadController::class, 'upload'])
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

        // Cash Wallet endpoints
        Route::prefix('cash-wallet')->name('cash-wallet.')->group(function () {
            // Wallet information
            Route::get('/', [CashWalletController::class, 'show'])->name('show');
            Route::get('/summary', [CashWalletController::class, 'summary'])->name('summary');

            // Tuition plan
            Route::get('/tuition-plan', [CashWalletController::class, 'tuitionPlan'])->name('tuition-plan');

            // Transactions
            Route::prefix('transactions')->name('transactions.')->group(function () {
                Route::get('/', [CashWalletController::class, 'transactions'])->name('index');
                Route::get('/recent', [CashWalletController::class, 'recentTransactions'])->name('recent');
                Route::get('/stats', [CashWalletController::class, 'stats'])->name('stats');
            });

            // Invoices
            Route::prefix('invoices')->name('invoices.')->group(function () {
                Route::get('/', [CashWalletController::class, 'invoices'])->name('index');
                Route::get('/{invoice}', [CashWalletController::class, 'invoiceDetail'])->name('show');
            });
        });

        // Club endpoints
        Route::prefix('clubs')->name('clubs.')->group(function () {
            // General club operations (available to all students)
            Route::get('/', [ClubController::class, 'index'])->name('index');
            Route::get('/my-memberships', [ClubController::class, 'myMemberships'])->name('my-memberships');
            Route::get('/{club}', [ClubController::class, 'show'])->name('show');
            Route::post('/{club}/apply', [ClubController::class, 'apply'])->name('apply');

            // Club management operations (president only)
            Route::get('/{club}/manage', [ClubManagementController::class, 'managementDashboard'])->name('manage');
            Route::put('/{club}', [ClubManagementController::class, 'update'])->name('update');
            Route::get('/{club}/members', [ClubManagementController::class, 'members'])->name('members');
            Route::put('/{club}/members/{member}/approve', [ClubManagementController::class, 'approveMember'])->name('approve-member');
            Route::put('/{club}/members/{member}/reject', [ClubManagementController::class, 'rejectMember'])->name('reject-member');
            Route::put('/{club}/members/{member}/role', [ClubManagementController::class, 'updateMemberRole'])->name('update-member-role');
        });

        // Event endpoints
        Route::prefix('events')->name('events.')->group(function () {
            // Event discovery and details
            Route::get('/', [\App\Http\Controllers\Api\V1\Student\EventController::class, 'index'])->name('index');
            Route::get('/{event}', [\App\Http\Controllers\Api\V1\Student\EventController::class, 'show'])->name('show');

            // Event registration
            Route::post('/{event}/register', [\App\Http\Controllers\Api\V1\Student\EventController::class, 'register'])->name('register');
            Route::delete('/{event}/register', [\App\Http\Controllers\Api\V1\Student\EventController::class, 'unregister'])->name('unregister');

            // Student participation history
            Route::get('/my/participations', [\App\Http\Controllers\Api\V1\Student\EventController::class, 'myEvents'])->name('my-events');
            Route::get('/my/{event}', [\App\Http\Controllers\Api\V1\Student\EventController::class, 'myEventDetails'])->name('my-event-details');
        });
    }); // End either middleware group

}); // End auth:sanctum group
