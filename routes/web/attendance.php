<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Attendance resource routes
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->middleware('can:view_course_offering')
        ->name('attendance.index');

    Route::get('attendance/create', [AttendanceController::class, 'create'])
        ->middleware('can:create_course_offering')
        ->name('attendance.create');

    Route::get('attendance/{attendance}/edit', [AttendanceController::class, 'edit'])
        ->middleware('can:edit_course_offering')
        ->name('attendance.edit');

    Route::post('attendance', [AttendanceController::class, 'store'])
        ->middleware('can:create_course_offering')
        ->name('attendance.store');

    Route::get('attendance/{attendance}', [AttendanceController::class, 'show'])
        ->middleware('can:view_course_offering')
        ->name('attendance.show');

    Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])
        ->middleware('can:edit_course_offering')
        ->name('attendance.update');

    Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])
        ->middleware('can:delete_course_offering')
        ->name('attendance.destroy');

    // Bulk operations
    Route::post('api/attendance/bulk-update', [AttendanceController::class, 'bulkUpdate'])
        ->middleware('can:edit_course_offering')
        ->name('attendance.api.bulk-update');
});
