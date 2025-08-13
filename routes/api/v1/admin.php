<?php

use App\Http\Controllers\Api\V1\Admin\EmailConfigurationController;
use App\Http\Controllers\Api\V1\Admin\EmailController;
use App\Http\Controllers\Api\V1\Admin\EmailTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    
    // Email Configuration Management
    Route::prefix('email-configurations')->group(function () {
        Route::get('/', [EmailConfigurationController::class, 'index'])->name('email-configurations.index');
        Route::post('/', [EmailConfigurationController::class, 'store'])->name('email-configurations.store');
        Route::get('/{configuration}', [EmailConfigurationController::class, 'show'])->name('email-configurations.show');
        Route::put('/{configuration}', [EmailConfigurationController::class, 'update'])->name('email-configurations.update');
        Route::delete('/{configuration}', [EmailConfigurationController::class, 'destroy'])->name('email-configurations.destroy');
        Route::post('/{configuration}/test', [EmailConfigurationController::class, 'test'])->name('email-configurations.test');
        Route::post('/{configuration}/activate', [EmailConfigurationController::class, 'setActive'])->name('email-configurations.activate');
        Route::get('/{configuration}/export', [EmailConfigurationController::class, 'export'])->name('email-configurations.export');
        Route::post('/import', [EmailConfigurationController::class, 'import'])->name('email-configurations.import');
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
    });
});
