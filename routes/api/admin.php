<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentApplicationController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Web\StudentApplicationController as WebStudentApplicationController;
use App\Http\Controllers\Web\StudentController as WebStudentController;
use Illuminate\Support\Facades\Route;

// Public authentication routes (Password-based login only - Google login moved to specific controllers)
Route::name('api.')->group(function () {
    Route::post('v1/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('v1/auth/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    Route::post('v1/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh')->middleware('auth:sanctum');
});

// Admin API routes (for internal use)
Route::middleware(['web'])->name('api.admin.')->group(function () {
    // Student Management
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/stats', [StudentController::class, 'stats'])->name('students.stats');
    Route::get('students/search', [WebStudentController::class, 'apiSearch'])->name('students.apiSearch');
    Route::get('students/{student}', [WebStudentController::class, 'apiShow'])->name('students.apiShow');

    // Student Applications
    Route::patch('/student-applications/bulk/status', [WebStudentApplicationController::class, 'updateBulkStatus']);

});

// Admin API routes (for external use)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Student Applications
    Route::get('/student-applications', [StudentApplicationController::class, 'index']);
    Route::get('/student-applications/{studentApplication}', [StudentApplicationController::class, 'show']);
    Route::patch('/student-applications/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus']);
    Route::post('/student-applications', [StudentApplicationController::class, 'store']);
});
