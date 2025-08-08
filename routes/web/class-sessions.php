<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\ClassSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Class Sessions resource routes
    Route::get('class-sessions', [ClassSessionController::class, 'index'])
        ->middleware('can:view_course_offering')
        ->name('class-sessions.index');

    Route::get('class-sessions/create', [ClassSessionController::class, 'create'])
        ->middleware('can:create_course_offering')
        ->name('class-sessions.create');

    Route::get('class-sessions/{classSession}/edit', [ClassSessionController::class, 'edit'])
        ->middleware('can:edit_course_offering')
        ->name('class-sessions.edit');

    Route::post('class-sessions', [ClassSessionController::class, 'store'])
        ->middleware('can:create_course_offering')
        ->name('class-sessions.store');

    Route::get('class-sessions/{classSession}', [ClassSessionController::class, 'show'])
        ->middleware('can:view_course_offering')
        ->name('class-sessions.show');

    Route::put('class-sessions/{classSession}', [ClassSessionController::class, 'update'])
        ->middleware('can:edit_course_offering')
        ->name('class-sessions.update');

    Route::delete('class-sessions/{classSession}', [ClassSessionController::class, 'destroy'])
        ->middleware('can:delete_course_offering')
        ->name('class-sessions.destroy');

    // Generate attendance for a class session
    Route::post('class-sessions/{classSession}/generate-attendance', [ClassSessionController::class, 'generateAttendance'])
        ->middleware('can:create_course_offering')
        ->name('class-sessions.generate-attendance');

    // Export attendance to CSV
    Route::get('class-sessions/{classSession}/export-attendance', [ClassSessionController::class, 'exportAttendance'])
        ->middleware('can:view_course_offering')
        ->name('class-sessions.export-attendance');

    // Attendance resource routes
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->middleware('can:view_course_offering')
        ->name('attendance.index');

    Route::get('attendance/create', [AttendanceController::class, 'create'])
        ->middleware('can:create_course_offering')
        ->name('attendance.create');

    Route::get('attendance/{attendance}', [AttendanceController::class, 'show'])
        ->middleware('can:view_course_offering')
        ->name('attendance.show');

    Route::get('attendance/{attendance}/edit', [AttendanceController::class, 'edit'])
        ->middleware('can:edit_course_offering')
        ->name('attendance.edit');

    Route::post('attendance', [AttendanceController::class, 'store'])
        ->middleware('can:create_course_offering')
        ->name('attendance.store');

    Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])
        ->middleware('can:edit_course_offering')
        ->name('attendance.update');

    Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])
        ->middleware('can:delete_course_offering')
        ->name('attendance.destroy');

    // Bulk attendance operations
    Route::post('attendance/bulk-update', [AttendanceController::class, 'bulkUpdate'])
        ->middleware('can:edit_course_offering')
        ->name('attendance.bulk-update');
});
