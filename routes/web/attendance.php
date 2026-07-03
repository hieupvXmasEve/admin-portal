<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Cross-offering reporting only (ADR 0013 phase B) — per-offering
    // attendance recording moved to the Course Offering Cockpit
    // (RecordClassSessionAttendanceController).
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->middleware('can:view_course_offering')
        ->name('attendance.index');
});
