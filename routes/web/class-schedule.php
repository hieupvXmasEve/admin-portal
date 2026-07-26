<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Admin Schedule Management Routes
Route::middleware(['auth', 'verified'])->name('schedules.')->group(function () {
    Route::get('/schedule-management', function () {
        return Inertia::render('ClassSchedule/Index');
    })->name('index');
});
