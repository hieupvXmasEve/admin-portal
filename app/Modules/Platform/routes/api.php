<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Api\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

Route::name('system-config.')->group(function (): void {
    Route::get('/system-config', [SystemConfigurationController::class, 'index'])->name('index');
    Route::get('/system-config/{key}', [SystemConfigurationController::class, 'show'])->name('show');
});

Route::middleware(['web', 'auth', 'campus.selected', 'can:manage_system_config'])
    ->name('system-config.')
    ->group(function (): void {
        Route::put('/system-config', [SystemConfigurationController::class, 'update'])->name('update');
        Route::post('/system-config/upload', [SystemConfigurationController::class, 'upload'])->name('upload');
    });
