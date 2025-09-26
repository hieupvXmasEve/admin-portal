<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Event management routes

    // QR Scanner interface
    Route::get('events/{event}/scanner', [EventController::class, 'scanner'])
        ->name('events.scanner');

    // Additional event actions
    Route::post('events/{event}/publish', [EventController::class, 'publish'])
        ->name('events.publish');

    Route::post('events/{event}/cancel', [EventController::class, 'cancel'])
        ->name('events.cancel');

    Route::post('events/{event}/complete', [EventController::class, 'complete'])
        ->name('events.complete');

    Route::resource('events', EventController::class);
});
