<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\ClassSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Class Sessions resource routes
    Route::get('class-sessions', [ClassSessionController::class, 'index'])
        ->middleware('can:view_class_session')
        ->name('class-sessions.index');

    Route::get('class-sessions/create', [ClassSessionController::class, 'create'])
        ->middleware('can:create_class_session')
        ->name('class-sessions.create');

    Route::get('class-sessions/{classSession}/edit', [ClassSessionController::class, 'edit'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.edit');

    Route::post('class-sessions', [ClassSessionController::class, 'store'])
        ->middleware('can:create_class_session')
        ->name('class-sessions.store');

    Route::get('class-sessions/{classSession}', [ClassSessionController::class, 'show'])
        ->middleware('can:view_class_session')
        ->name('class-sessions.show');

    Route::put('class-sessions/{classSession}', [ClassSessionController::class, 'update'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.update');

    Route::delete('class-sessions/{classSession}', [ClassSessionController::class, 'destroy'])
        ->middleware('can:delete_class_session')
        ->name('class-sessions.destroy');

    // Bulk delete (web route — for Inertia router.delete with only:[])
    Route::delete('class-sessions', [ClassSessionController::class, 'bulkDestroy'])
        ->middleware('can:delete_class_session')
        ->name('class-sessions.bulk-destroy');

    // Generate sessions for a course offering (web route — for Inertia useForm)
    Route::post('course-offerings/{courseOffering}/class-sessions/generate', [ClassSessionController::class, 'generate'])
        ->middleware('can:generate_class_session')
        ->name('class-sessions.generate');

    // Modal pages — route-based modals via @inertiaui/modal-vue
    Route::get('course-offerings/{courseOffering}/class-sessions/add', [ClassSessionController::class, 'createForOffering'])
        ->middleware('can:create_class_session')
        ->name('class-sessions.add-for-offering');

    Route::get('class-sessions/{classSession}/quick-edit', [ClassSessionController::class, 'editModal'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.quick-edit');

    Route::get('course-offerings/{courseOffering}/class-sessions/bulk-edit', [ClassSessionController::class, 'bulkEditModal'])
        ->middleware('can:edit_class_session')
        ->name('class-sessions.bulk-edit');

    // Generate attendance for a class session
    Route::post('class-sessions/{classSession}/generate-attendance', [ClassSessionController::class, 'generateAttendance'])
        ->middleware('can:generate_class_session_attendance')
        ->name('class-sessions.generate-attendance');

    // Export attendance to CSV
    Route::get('class-sessions/{classSession}/export-attendance', [ClassSessionController::class, 'exportAttendance'])
        ->middleware('can:export_class_session_attendance')
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
