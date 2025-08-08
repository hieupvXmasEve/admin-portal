<?php

use App\Http\Controllers\Web\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'campus.selected'])->group(function () {
    Route::get('/systems/activity-logs', [ActivityLogController::class, 'index'])->name('system.activity-logs.index');
});
