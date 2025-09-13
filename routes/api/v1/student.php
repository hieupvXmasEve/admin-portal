<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Student\AttendanceController;
use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Controllers\Api\V1\Student\CalendarController;
use App\Http\Controllers\Api\V1\Student\CourseRegistrationController;
use App\Http\Controllers\Api\V1\Student\CurriculumController;
use App\Http\Controllers\Api\V1\Student\DashboardController;
use App\Http\Controllers\Api\V1\Student\GradeController;
use App\Http\Controllers\Api\V1\Student\NotificationController;
use App\Http\Controllers\Api\V1\Student\ProfileController;
use App\Http\Controllers\Api\V1\Student\TimetableController;
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

        // Course registration endpoints (TODO)
        Route::prefix('course-registration')->name('course-registration.')->group(function () {
            // TODO: complete this endpoint
            Route::get('/available-courses', [CourseRegistrationController::class, 'availableCourses'])
                ->name('available-courses');
            // TODO: complete this endpoint
            Route::post('/register', [CourseRegistrationController::class, 'register'])
                ->middleware(['student.api.rate:student-registration'])
                ->name('register');

            Route::delete('/drop/{registration}', [CourseRegistrationController::class, 'drop'])
                ->middleware(['student.api.rate:student-registration'])
                ->name('drop');

            // TODO: complete this endpoint
            Route::get('/my-registrations', [CourseRegistrationController::class, 'myRegistrations'])
                ->name('my-registrations');

            Route::post('/validate-registration', [CourseRegistrationController::class, 'validateRegistration'])
                ->name('validate-registration');

            Route::get('/schedule-conflicts/{courseOfferingId}', [CourseRegistrationController::class, 'checkScheduleConflicts'])
                ->name('schedule-conflicts');
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

        // Attendance endpoints
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
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
            Route::get('/prerequisite-tree', [CurriculumController::class, 'prerequisiteTree'])->name('prerequisite-tree');
            Route::get('/program-requirements', [CurriculumController::class, 'programRequirements'])->name('program-requirements');
            Route::get('/roadmap', [CurriculumController::class, 'roadmap'])->name('roadmap');
        });

        // Notification endpoints
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
            Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('update-preferences');
            Route::post('/push-subscription', [NotificationController::class, 'subscribeToPush'])->name('push-subscription');
        });

        // Academic calendar endpoints
        Route::prefix('calendar')->name('calendar.')->group(function () {
            Route::get('/semesters', [CalendarController::class, 'semesters'])->name('semesters');
            Route::get('/semester/{semester}/deadlines', [CalendarController::class, 'semesterDeadlines'])->name('semester-deadlines');
            Route::get('/academic-calendar', [CalendarController::class, 'academicCalendar'])->name('academic-calendar');
            Route::get('/current-semester', [CalendarController::class, 'currentSemester'])->name('current-semester');
        });
    }); // End either middleware group

}); // End auth:sanctum group
