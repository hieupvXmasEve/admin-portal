<?php

use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\EmailConfigurationController;
use App\Http\Controllers\Web\SystemConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'campus.selected'])->group(function () {
    Route::get('/systems/config', [SystemConfigController::class, 'index'])->name('system.config.index');
    Route::get('/systems/activity-logs', [ActivityLogController::class, 'index'])->name('system.activity-logs.index');
    Route::get('/systems/email-configuration', [EmailConfigurationController::class, 'index'])->name('system.email-configuration.index');

    // Email Templates
    Route::get('/systems/email-templates', [EmailConfigurationController::class, 'templates'])->name('system.email-templates.index');
    Route::get('/systems/email-templates/create', [EmailConfigurationController::class, 'createTemplate'])->name('system.email-templates.create');
    Route::get('/systems/email-templates/{template}/edit', [EmailConfigurationController::class, 'editTemplate'])->name('system.email-templates.edit');

    Route::get('/systems/bulk-email', [EmailConfigurationController::class, 'bulkEmail'])->name('system.bulk-email.index');
});
