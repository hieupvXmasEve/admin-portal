<?php

use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\EmailConfigurationController;
use App\Http\Controllers\Web\SystemConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'campus.selected'])->group(function () {
    Route::get('/systems/config', [SystemConfigController::class, 'index'])->name('system.config.index');
    Route::get('/systems/activity-logs', [ActivityLogController::class, 'index'])->name('system.activity-logs.index');
    Route::get('/systems/email-configuration', [EmailConfigurationController::class, 'index'])->name('system.email-configuration.index');
    Route::get('/systems/email-templates', [EmailConfigurationController::class, 'templates'])->name('system.email-templates.index');
    Route::get('/systems/bulk-email', [EmailConfigurationController::class, 'bulkEmail'])->name('system.bulk-email.index');
});
