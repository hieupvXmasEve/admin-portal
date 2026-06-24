<?php

declare(strict_types=1);

use App\Modules\AI\Http\Web\Admin\AiProviderSettingsController;
use App\Modules\AI\Http\Web\Admin\StaffCopilotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('ai')
    ->name('ai.')
    ->group(function (): void {
        Route::get('/copilot', [StaffCopilotController::class, 'index'])
            ->middleware('can:view_ai_metrics')
            ->name('copilot.index');

        Route::post('/copilot/messages', [StaffCopilotController::class, 'store'])
            ->middleware('can:view_ai_metrics')
            ->name('copilot.messages.store');

        Route::get('/copilot/runs/{run}/events', [StaffCopilotController::class, 'events'])
            ->middleware('can:view_ai_metrics')
            ->name('copilot.runs.events');

        Route::post('/copilot/runs/{run}/cancel', [StaffCopilotController::class, 'cancel'])
            ->middleware('can:view_ai_metrics')
            ->name('copilot.runs.cancel');

        Route::post('/copilot/runs/{run}/retry', [StaffCopilotController::class, 'retry'])
            ->middleware('can:view_ai_metrics')
            ->name('copilot.runs.retry');

        Route::get('/provider-settings', [AiProviderSettingsController::class, 'index'])
            ->middleware('can:view_ai_provider_settings')
            ->name('provider-settings.index');

        Route::put('/provider-settings', [AiProviderSettingsController::class, 'update'])
            ->name('provider-settings.update');

        Route::post('/provider-settings/test', [AiProviderSettingsController::class, 'test'])
            ->name('provider-settings.test');

        Route::delete('/provider-settings/key', [AiProviderSettingsController::class, 'destroyKey'])
            ->name('provider-settings.key.destroy');
    });
