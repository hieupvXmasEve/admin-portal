<?php

use App\Http\Controllers\Api\AdminScheduleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentWalletController;
use App\Http\Controllers\Api\V1\Admin\EmailConfigurationController;
use App\Http\Controllers\Api\V1\Admin\EmailController;
use App\Http\Controllers\Api\V1\Admin\EmailTemplateController;
use App\Http\Controllers\Web\DashboardController;
use App\Modules\Academic\FacultyWorkforce\Http\Web\LectureController;
use App\Modules\Academic\Http\Web\StudentController as WebStudentController;
use App\Modules\Notification\Http\Api\V1\Admin\NotificationTemplateController as NotificationTemplateApiController;
use Illuminate\Support\Facades\Route;

// Public authentication routes (Password-based login only - Google login moved to specific controllers)
Route::name('api.')->group(function () {
    Route::post('v1/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('v1/auth/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    Route::post('v1/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh')->middleware('auth:sanctum');
});

// Admin API routes (for internal use)
Route::middleware(['web', 'auth'])->name('api.admin.')->group(function () {
    // Student Management
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/stats', [StudentController::class, 'stats'])->name('students.stats');
    Route::get('students/search', [WebStudentController::class, 'apiSearch'])->name('students.apiSearch');
    Route::post('students/by-ids', [WebStudentController::class, 'getByStudentIds'])->name('students.getByStudentIds');
    Route::get('students/{student}', [WebStudentController::class, 'apiShow'])->name('students.apiShow');

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

    // Lectures API - for dropdowns and quick edits
    Route::get('/lectures', [LectureController::class, 'apiIndex'])->name('lectures.api-index');

    // Student Wallet Management (Admin)
    Route::prefix('wallet')->name('wallet.')->group(function () {
        // Student-specific gold wallet operations
        Route::prefix('gold/students/{student}')->name('students.')->group(function () {
            Route::post('/adjust', [StudentWalletController::class, 'adjustBalance'])->name('adjust');
        });
        // Student cash wallet here
    });

    // API for Web Modals
    require __DIR__.'/admin/academic.php';
});

// Dashboard API routes - Separated for performance
Route::middleware(['web', 'auth'])->prefix('dashboard')->name('api.admin.dashboard.')->group(function () {
    // Chart 1: Student distribution by campus/program
    Route::get('/student-distribution', [DashboardController::class, 'studentDistribution'])->name('student-distribution');

    // Chart 2: Enrollment growth/trend by term
    Route::get('/enrollment-growth', [DashboardController::class, 'enrollmentGrowth'])->name('enrollment-growth');

    // Chart 3: Academic standings distribution
    Route::get('/academic-standing', [DashboardController::class, 'academicStanding'])->name('academic-standing');

    // Chart 4: Graduation rate analysis
    Route::get('/graduation-rate', [DashboardController::class, 'graduationRate'])->name('graduation-rate');

    // Additional dashboard endpoints
    Route::get('/recent-activities', [DashboardController::class, 'recentActivities'])->name('recent-activities');
});

Route::middleware(['web', 'auth'])->prefix('admin')->name('api.admin.')->group(function () {
    require __DIR__.'/admin/notification.php';

    Route::prefix('notification-templates')->name('notification-templates.')->group(function () {
        Route::put('/{template}', [NotificationTemplateApiController::class, 'update'])->name('update');
        Route::get('/variables/{type_key}', [NotificationTemplateApiController::class, 'variables'])->name('variables');
        Route::post('/{template}/preview', [NotificationTemplateApiController::class, 'preview'])->name('preview');
        Route::post('/{template}/test-send', [NotificationTemplateApiController::class, 'testSend'])->middleware('throttle:notification-template-test-send')->name('test-send');
    });
});
