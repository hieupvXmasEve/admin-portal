<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Web\Admin\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'campus.selected', 'can:view_system_config'])->group(function (): void {
    Route::get('/systems/config', [SystemConfigurationController::class, 'index'])
        ->name('system.config.index');

    Route::put('/systems/config', [SystemConfigurationController::class, 'update'])
        ->name('system.config.update');
    Route::post('/systems/config/branding', [SystemConfigurationController::class, 'upload'])
        ->name('system.config.upload');
});
