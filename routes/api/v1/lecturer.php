<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Lecturer\AuthController;
use App\Http\Controllers\Api\V1\Lecturer\DashboardController;
use App\Http\Controllers\Api\V1\Lecturer\CourseController;
use App\Http\Controllers\Api\V1\Lecturer\AttendanceController;
use App\Http\Controllers\Api\V1\Lecturer\TimetableController;
use App\Http\Controllers\Api\V1\Lecturer\StudentController;

/*
|--------------------------------------------------------------------------
| Lecturer API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for lecturer-related functionality.
| These routes are loaded by the main API routes file.
|
*/

// Public authentication routes
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['lecturer.api.rate:lecturer-auth'])
        ->name('login');

    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware(['lecturer.api.rate:lecturer-auth'])
        ->name('refresh');
});

// Protected lecturer API routes
Route::middleware([
    'auth:sanctum',
    'lecturer.api.auth',
    'lecturer.api.rate:lecturer-api',
    'api.logging'
])->group(function () {

    // Authentication management
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });

    // Dashboard endpoints
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware(['lecturer.api.rate:lecturer-dashboard'])
            ->name('index');

        Route::get('/teaching-summary', [DashboardController::class, 'teachingSummary'])->name('teaching-summary');
        Route::get('/attendance-overview', [DashboardController::class, 'attendanceOverview'])->name('attendance-overview');
        Route::get('/student-alerts', [DashboardController::class, 'studentAlerts'])->name('student-alerts');
        Route::get('/upcoming-sessions', [DashboardController::class, 'upcomingSessions'])->name('upcoming-sessions');
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities'])->name('recent-activities');
    });

    // Course management endpoints
    Route::prefix('courses')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])
            ->middleware(['lecturer.api.rate:lecturer-course-management'])
            ->name('index');

        Route::get('/summary', [CourseController::class, 'summary'])->name('summary');
        Route::get('/filter-options', [CourseController::class, 'filterOptions'])->name('filter-options');

        Route::get('/{courseOffering}', [CourseController::class, 'show'])->name('show');
        Route::get('/{courseOffering}/unit', [CourseController::class, 'unit'])->name('unit');
        Route::get('/{courseOffering}/statistics', [CourseController::class, 'statistics'])->name('statistics');
        Route::get('/{courseOffering}/students', [CourseController::class, 'students'])->name('students');
        Route::get('/{courseOffering}/sessions', [CourseController::class, 'sessions'])->name('sessions');
    });

    // Attendance management endpoints
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])
            ->middleware(['lecturer.api.rate:lecturer-attendance'])
            ->name('index');

        Route::get('/summary', [AttendanceController::class, 'summary'])->name('summary');
        Route::get('/alerts', [AttendanceController::class, 'alerts'])->name('alerts');
        Route::get('/sessions-requiring-attention', [AttendanceController::class, 'sessionsRequiringAttention'])->name('sessions-requiring-attention');

        Route::get('/sessions/{session}', [AttendanceController::class, 'sessionAttendance'])->name('session');

        Route::post('/sessions/{session}/mark', [AttendanceController::class, 'markAttendance'])->name('mark');
        Route::post('/bulk-mark', [AttendanceController::class, 'bulkMarkAttendance'])->name('bulk-mark');

        Route::get('/courses/{courseOffering}/analytics', [AttendanceController::class, 'courseAnalytics'])->name('course-analytics');
        Route::get('/courses/{courseOffering}/export', [AttendanceController::class, 'exportAttendance'])->name('export');
    });

    // Timetable and session management endpoints
    Route::prefix('timetable')->name('timetable.')->group(function () {
        Route::get('/', [TimetableController::class, 'index'])
            ->middleware(['lecturer.api.rate:lecturer-timetable'])
            ->name('index');

        Route::get('/summary', [TimetableController::class, 'summary'])->name('summary');
        Route::get('/upcoming-sessions', [TimetableController::class, 'upcomingSessions'])->name('upcoming-sessions');
        Route::get('/available-rooms', [TimetableController::class, 'availableRooms'])->name('available-rooms');
    });

    // Session management endpoints
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::post('/', [TimetableController::class, 'createSession'])->name('create');
        Route::get('/{session}', [TimetableController::class, 'sessionDetails'])->name('show');
        Route::put('/{session}', [TimetableController::class, 'updateSession'])->name('update');
        Route::delete('/{session}', [TimetableController::class, 'cancelSession'])->name('cancel');
        Route::post('/bulk-update', [TimetableController::class, 'bulkUpdateSessions'])->name('bulk-update');
    });

    // Student management endpoints
    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])
            ->middleware(['lecturer.api.rate:lecturer-student-management'])
            ->name('index');

        Route::get('/summary', [StudentController::class, 'summary'])->name('summary');
        Route::get('/alerts', [StudentController::class, 'alerts'])->name('alerts');
        Route::get('/requires-attention', [StudentController::class, 'requiresAttention'])->name('requires-attention');
        Route::get('/analytics', [StudentController::class, 'analytics'])->name('analytics');
        Route::post('/bulk-actions', [StudentController::class, 'bulkActions'])->name('bulk-actions');

        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::post('/{student}/notes', [StudentController::class, 'addNote'])->name('add-note');
        Route::put('/{student}/notes/{note}', [StudentController::class, 'updateNote'])->name('update-note');
        Route::delete('/{student}/notes/{note}', [StudentController::class, 'deleteNote'])->name('delete-note');
    });
});
