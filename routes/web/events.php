<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Event management routes

    // QR Scanner interface
    Route::get('events/{event}/scanner', [EventController::class, 'scanner'])
        ->name('events.scanner');

    // Manual participant management interface
    Route::get('events/{event}/manage-participants', [EventController::class, 'manageParticipants'])
        ->name('events.manage-participants');

    // Additional event actions
    Route::post('events/{event}/publish', [EventController::class, 'publish'])
        ->name('events.publish');

    Route::post('events/{event}/cancel', [EventController::class, 'cancel'])
        ->name('events.cancel');

    Route::post('events/{event}/complete', [EventController::class, 'complete'])
        ->name('events.complete');

    // Event reporting and analytics routes
    Route::prefix('events')->name('events.')->group(function () {
        Route::get('reports', [EventReportController::class, 'index'])
            ->name('reports');

        Route::get('reports/analytics', [EventReportController::class, 'analytics'])
            ->name('reports.analytics');

        Route::get('reports/export', [EventReportController::class, 'exportEvents'])
            ->name('reports.export');

        Route::get('{event}/stats', [EventReportController::class, 'eventStats'])
            ->name('stats');

        Route::get('{event}/export-participants', [EventReportController::class, 'exportParticipants'])
            ->name('export-participants');

        Route::get('reports/history', [EventReportController::class, 'eventHistory'])
            ->name('reports.history');

        // Route::resource('events', EventController::class);
        // index
        Route::get('/list', [EventController::class, 'index'])
            ->name('index');
        Route::get('create', [EventController::class, 'create'])
            ->name('create');
        Route::get('create-manual', [EventController::class, 'createManual'])
            ->name('create-manual');
        Route::post('store', [EventController::class, 'store'])
            ->name('store');
        Route::post('store-manual', [EventController::class, 'storeManual'])
            ->name('store-manual');
        Route::get('{event}', [EventController::class, 'show'])
            ->name('show');
        Route::get('{event}/edit', [EventController::class, 'edit'])
            ->name('edit');
        Route::put('{event}', [EventController::class, 'update'])
            ->name('update');
        Route::delete('{event}', [EventController::class, 'destroy'])
            ->name('destroy');
    });
});
