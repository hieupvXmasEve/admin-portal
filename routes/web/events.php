<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Event management routes

    // QR Scanner interface
    Route::get('events/{event}/scanner', [EventController::class, 'scanner'])
        ->middleware('can:checkin_event')
        ->name('events.scanner');

    // Manual participant management interface
    Route::get('events/{event}/manage-participants', [EventController::class, 'manageParticipants'])
        ->middleware('can:manage_participants')
        ->name('events.manage-participants');

    // Additional event actions
    Route::post('events/{event}/publish', [EventController::class, 'publish'])
        ->middleware('can:publish_event')
        ->name('events.publish');

    Route::post('events/{event}/cancel', [EventController::class, 'cancel'])
        ->middleware('can:cancel_event')
        ->name('events.cancel');

    Route::post('events/{event}/complete', [EventController::class, 'complete'])
        ->middleware('can:complete_event')
        ->name('events.complete');

    // Event reporting and analytics routes
    Route::prefix('events')->name('events.')->group(function () {
        Route::get('reports', [EventReportController::class, 'index'])
            ->middleware('can:view_event')
            ->name('reports');

        Route::get('reports/analytics', [EventReportController::class, 'analytics'])
            ->middleware('can:view_event')
            ->name('reports.analytics');

        Route::get('reports/export', [EventReportController::class, 'exportEvents'])
            ->middleware('can:view_event')
            ->name('reports.export');

        Route::get('{event}/stats', [EventReportController::class, 'eventStats'])
            ->middleware('can:view_event')
            ->name('stats');

        Route::get('{event}/export-participants', [EventReportController::class, 'exportParticipants'])
            ->middleware('can:view_event')
            ->name('export-participants');

        Route::get('reports/history', [EventReportController::class, 'eventHistory'])
            ->middleware('can:view_event')
            ->name('reports.history');

        // Event CRUD routes
        Route::get('/list', [EventController::class, 'index'])
            ->middleware('can:view_event')
            ->name('index');

        Route::get('create', [EventController::class, 'create'])
            ->middleware('can:create_event')
            ->name('create');

        Route::get('create-manual', [EventController::class, 'createManual'])
            ->middleware('can:create_event')
            ->name('create-manual');

        Route::post('store', [EventController::class, 'store'])
            ->middleware('can:create_event')
            ->name('store');

        Route::post('store-manual', [EventController::class, 'storeManual'])
            ->middleware('can:create_event')
            ->name('store-manual');

        Route::get('{event}', [EventController::class, 'show'])
            ->middleware('can:show_event')
            ->name('show');

        Route::get('{event}/edit', [EventController::class, 'edit'])
            ->middleware('can:update_event')
            ->name('edit');

        Route::put('{event}', [EventController::class, 'update'])
            ->middleware('can:update_event')
            ->name('update');

        Route::delete('{event}', [EventController::class, 'destroy'])
            ->middleware('can:delete_event')
            ->name('destroy');
    });
});
