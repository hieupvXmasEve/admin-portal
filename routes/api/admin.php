<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentApplicationController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\V1\Admin\EmailConfigurationController;
use App\Http\Controllers\Api\V1\Admin\EmailController;
use App\Http\Controllers\Api\V1\Admin\EmailTemplateController;
use App\Http\Controllers\Web\StudentApplicationController as WebStudentApplicationController;
use App\Http\Controllers\Web\StudentController as WebStudentController;
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
    Route::get('students/{student}', [WebStudentController::class, 'apiShow'])->name('students.apiShow');

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
});

// Admin API routes (for external use)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Student Applications
    Route::get('/student-applications', [StudentApplicationController::class, 'index']);
    Route::get('/student-applications/{studentApplication}', [StudentApplicationController::class, 'show']);
    Route::patch('/student-applications/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus']);
    Route::post('/student-applications', [StudentApplicationController::class, 'store']);

});
