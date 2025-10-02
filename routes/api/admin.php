<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminScheduleController;
use App\Http\Controllers\Api\AdminStudentImpersonationController;
use App\Http\Controllers\Api\AdminLecturerImpersonationController;
use App\Http\Controllers\Api\StudentApplicationController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\V1\Admin\EmailConfigurationController;
use App\Http\Controllers\Api\V1\Admin\EmailController;
use App\Http\Controllers\Api\V1\Admin\EmailTemplateController;
use App\Http\Controllers\Web\StudentApplicationController as WebStudentApplicationController;
use App\Http\Controllers\Web\StudentController as WebStudentController;
use App\Http\Controllers\Web\FormController;
use App\Http\Controllers\Api\StudentWalletController;
use Illuminate\Support\Facades\Route;

// Public authentication routes (Password-based login only - Google login moved to specific controllers)
Route::name('api.')->group(function () {
    Route::post('v1/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('v1/auth/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    Route::post('v1/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh')->middleware('auth:sanctum');
});

// Admin API routes (for internal use)
Route::middleware(['web'])->name('api.admin.')->group(function () {
    // Student Management
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/stats', [StudentController::class, 'stats'])->name('students.stats');
    Route::get('students/search', [WebStudentController::class, 'apiSearch'])->name('students.apiSearch');
    Route::post('students/by-ids', [WebStudentController::class, 'getByStudentIds'])->name('students.getByStudentIds');
    Route::get('students/{student}', [WebStudentController::class, 'apiShow'])->name('students.apiShow');

    // Student Impersonation
    Route::post('/students/impersonate', [AdminStudentImpersonationController::class, 'impersonateStudent'])->name('students.impersonate');
    Route::get('/students/impersonation-sessions', [AdminStudentImpersonationController::class, 'getImpersonationSessions'])->name('students.impersonation-sessions');

    // Lecturer Impersonation
    Route::post('/lecturers/impersonate', [AdminLecturerImpersonationController::class, 'impersonateLecturer'])->name('lecturers.impersonate');
    Route::get('/lecturers/impersonation-sessions', [AdminLecturerImpersonationController::class, 'getImpersonationSessions'])->name('lecturers.impersonation-sessions');

    // Student Applications
    Route::patch('/student-applications/bulk/status', [WebStudentApplicationController::class, 'updateBulkStatus']);

    // Email Configuration Management
    Route::prefix('email-configurations')->group(function () {
        Route::get('/', [EmailConfigurationController::class, 'index'])->name('email-configurations.index');
        Route::post('/', [EmailConfigurationController::class, 'store'])->name('email-configurations.store');
        Route::get('/{configuration}', [EmailConfigurationController::class, 'show'])->name('email-configurations.show');
        Route::put('/{configuration}', [EmailConfigurationController::class, 'update'])->name('email-configurations.update');
        Route::delete('/{configuration}', [EmailConfigurationController::class, 'destroy'])->name('email-configurations.destroy');
        Route::post('/{configuration}/test', [EmailConfigurationController::class, 'test'])->name('email-configurations.test');
        Route::post('/test-data', [EmailConfigurationController::class, 'testData'])->name('email-configurations.test-data');
        Route::post('/{configuration}/activate', [EmailConfigurationController::class, 'setActive'])->name('email-configurations.activate');
    });

    // Email Template Management
    Route::prefix('email-templates')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::post('/', [EmailTemplateController::class, 'store'])->name('email-templates.store');
        Route::get('/types', [EmailTemplateController::class, 'getTypes'])->name('email-templates.types');
        Route::post('/validate', [EmailTemplateController::class, 'validateTemplate'])->name('email-templates.validate');
        Route::get('/type/{type}', [EmailTemplateController::class, 'getByType'])->name('email-templates.by-type');
        Route::get('/{template}', [EmailTemplateController::class, 'show'])->name('email-templates.show');
        Route::put('/{template}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('/{template}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
        Route::post('/{template}/version', [EmailTemplateController::class, 'createVersion'])->name('email-templates.version');
        Route::post('/{template}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
    });

    // Email Sending and Management
    Route::prefix('emails')->group(function () {
        Route::post('/send', [EmailController::class, 'sendSingle'])->name('emails.send');
        Route::post('/send-bulk', [EmailController::class, 'sendBulk'])->name('emails.send-bulk');
        Route::post('/send-notification', [EmailController::class, 'sendNotification'])->name('emails.send-notification');
        Route::post('/schedule-reminder', [EmailController::class, 'scheduleReminder'])->name('emails.schedule-reminder');
        Route::get('/logs', [EmailController::class, 'logs'])->name('emails.logs');
        Route::get('/statistics', [EmailController::class, 'statistics'])->name('emails.statistics');
        Route::get('/logs/{emailLog}', [EmailController::class, 'showLog'])->name('emails.show-log');
        Route::post('/logs/{emailLog}/retry', [EmailController::class, 'retryEmail'])->name('emails.retry');
        Route::get('/user-roles', [EmailController::class, 'getUserRoles'])->name('emails.user-roles');
        Route::get('/campuses', [EmailController::class, 'getCampuses'])->name('emails.campuses');
    });

    // Schedule Management (Admin)
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [AdminScheduleController::class, 'index'])->name('index');
        Route::get('/filter-options', [AdminScheduleController::class, 'filterOptions'])->name('filter-options');
        Route::get('/{session}', [AdminScheduleController::class, 'show'])->name('show');
        Route::put('/{session}', [AdminScheduleController::class, 'update'])->name('update');
    });

    // Rooms API - for dropdowns and quick edits
    Route::get('/rooms', [\App\Http\Controllers\Web\RoomController::class, 'apiIndex'])->name('rooms.api-index');

    // Lectures API - for dropdowns and quick edits
    Route::get('/lectures', [\App\Http\Controllers\Web\LectureController::class, 'apiIndex'])->name('lectures.api-index');

    // Form management API routes
    Route::prefix('forms')->name('api.forms.')->group(function () {
        Route::get('/', [FormController::class, 'index'])->name('index');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('/metadata', [FormController::class, 'metadata'])->name('metadata');
        Route::get('/available', [FormController::class, 'available'])->name('available');

        Route::prefix('{form}')->group(function () {
            Route::get('/', [FormController::class, 'show'])->name('show');
            Route::put('/', [FormController::class, 'update'])->name('update');
            Route::delete('/', [FormController::class, 'destroy'])->name('destroy');

            // Form actions
            Route::post('/clone', [FormController::class, 'clone'])->name('clone');
            Route::post('/archive', [FormController::class, 'archive'])->name('archive');
            Route::post('/restore', [FormController::class, 'restore'])->name('restore');
            Route::get('/statistics', [FormController::class, 'statistics'])->name('statistics');

            // Version management
            Route::post('/versions/{version}/publish', [FormController::class, 'publish'])->name('version.publish');

            // Target management
            Route::post('/targets', [FormController::class, 'createTarget'])->name('targets.store');
        });
    });

    // Event Check-in Management (Admin)
    Route::prefix('events')->name('events.')->group(function () {
        Route::post('/search-student', [\App\Http\Controllers\Api\EventCheckinController::class, 'searchStudent'])->name('search-student');
        Route::post('/checkin', [\App\Http\Controllers\Api\EventCheckinController::class, 'checkinStudent'])->name('checkin');
        Route::get('/{event}/participants', [\App\Http\Controllers\Api\EventCheckinController::class, 'getParticipants'])->name('participants');
        Route::get('/{event}/statistics', [\App\Http\Controllers\Api\EventCheckinController::class, 'getStatistics'])->name('statistics');

        // Manual participant management
        Route::prefix('{event}/manual-participants')->name('manual-participants.')->group(function () {
            Route::get('/filter-options', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'getFilterOptions'])->name('filter-options');
            Route::post('/search-students', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'searchStudents'])->name('search-students');
            Route::post('/add', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'addParticipants'])->name('add');
            Route::get('/list', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'getParticipants'])->name('list');
            Route::put('/bulk-update-status', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'bulkUpdateStatus'])->name('bulk-update-status');
            Route::delete('/remove', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'removeParticipants'])->name('remove');
            Route::get('/statistics', [\App\Http\Controllers\Api\V1\EventParticipantController::class, 'getStatistics'])->name('statistics');
        });
    });

    // Student Wallet Management (Admin)
    Route::prefix('wallet')->name('wallet.')->group(function () {
        // Student-specific wallet operations
        Route::prefix('students/{student}')->name('students.')->group(function () {
            // Route::get('/', [StudentWalletController::class, 'showStudent'])->name('show');
            // Route::get('/summary', [StudentWalletController::class, 'summaryForStudent'])->name('summary');
            Route::post('/adjust', [StudentWalletController::class, 'adjustBalance'])->name('adjust');
            // Route::post('/check-balance', [StudentWalletController::class, 'checkBalance'])->name('check-balance');

        });
    });
});

// Admin API routes (for external use)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Student Applications
    Route::get('/student-applications', [StudentApplicationController::class, 'index']);
    Route::get('/student-applications/{studentApplication}', [StudentApplicationController::class, 'show']);
    Route::put('/student-applications/{studentApplication}', [StudentApplicationController::class, 'update']);
    Route::patch('/student-applications/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus']);
    Route::post('/student-applications', [StudentApplicationController::class, 'store']);
});

// Dashboard API routes - Separated for performance
Route::middleware(['web'])->prefix('dashboard')->name('api.admin.dashboard.')->group(function () {
    // Chart 1: Student distribution by campus/program
    Route::get('/student-distribution', [\App\Http\Controllers\Web\DashboardController::class, 'studentDistribution'])->name('student-distribution');

    // Chart 2: Enrollment growth/trend by term
    Route::get('/enrollment-growth', [\App\Http\Controllers\Web\DashboardController::class, 'enrollmentGrowth'])->name('enrollment-growth');

    // Chart 3: Academic standings distribution
    Route::get('/academic-standing', [\App\Http\Controllers\Web\DashboardController::class, 'academicStanding'])->name('academic-standing');

    // Chart 4: Graduation rate analysis
    Route::get('/graduation-rate', [\App\Http\Controllers\Web\DashboardController::class, 'graduationRate'])->name('graduation-rate');

    // Additional dashboard endpoints
    Route::get('/recent-activities', [\App\Http\Controllers\Web\DashboardController::class, 'recentActivities'])->name('recent-activities');
});
