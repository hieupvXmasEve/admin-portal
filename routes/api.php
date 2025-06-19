<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/login/google', [AuthController::class, 'loginWithGoogle'])->name('auth.login.google');
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

// Protected API routes for students
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'student'])->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    // Student Profile
    Route::get('/student/profile', [StudentController::class, 'profile'])->name('student.profile');
    Route::put('/student/profile', [StudentController::class, 'updateProfile'])->name('student.update-profile');
    Route::get('/student/academic-record', [StudentController::class, 'academicRecord'])->name('student.academic-record');
    Route::get('/student/holds', [StudentController::class, 'holds'])->name('student.holds');
    Route::get('/student/graduation-progress', [StudentController::class, 'graduationProgress'])->name('student.graduation-progress');

    // Course Information
    Route::get('/courses/available', [CourseController::class, 'available'])->name('courses.available');
    Route::get('/courses/{courseOffering}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{courseOffering}/prerequisites', [CourseController::class, 'prerequisites'])->name('courses.prerequisites');
    Route::get('/semesters', [CourseController::class, 'semesters'])->name('semesters.index');
    Route::get('/semesters/{semester}/courses', [CourseController::class, 'semesterCourses'])->name('semesters.courses');

    // Course Registration
    Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::get('/registrations/current', [RegistrationController::class, 'current'])->name('registrations.current');
    Route::post('/registrations', [RegistrationController::class, 'register'])->name('registrations.store');
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'drop'])->name('registrations.drop');
    Route::post('/registrations/{registration}/withdraw', [RegistrationController::class, 'withdraw'])->name('registrations.withdraw');
    Route::get('/registrations/eligibility/{courseOffering}', [RegistrationController::class, 'checkEligibility'])->name('registrations.eligibility');
    Route::get('/registrations/schedule-conflicts/{courseOffering}', [RegistrationController::class, 'checkScheduleConflicts'])->name('registrations.schedule-conflicts');

    // Academic History
    Route::get('/academic/transcript', [StudentController::class, 'transcript'])->name('academic.transcript');
    Route::get('/academic/gpa', [StudentController::class, 'gpa'])->name('academic.gpa');
    Route::get('/academic/credit-summary', [StudentController::class, 'creditSummary'])->name('academic.credit-summary');
});

// Admin API routes (for internal use)
Route::prefix('v1')->name('api.v1.admin.')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Student Management
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/search', [AdminStudentController::class, 'search'])->name('students.search');
    Route::post('/students/by-ids', [AdminStudentController::class, 'getByIds'])->name('students.by-ids');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    // Bulk operations
    Route::post('/students/bulk-import', [StudentController::class, 'bulkImport'])->name('students.bulk-import');
    Route::post('/students/bulk-update', [StudentController::class, 'bulkUpdate'])->name('students.bulk-update');

    // Registration Management
    Route::get('/registrations', [RegistrationController::class, 'adminIndex'])->name('registrations.index');
    Route::post('/registrations/{registration}/override', [RegistrationController::class, 'adminOverride'])->name('registrations.override');
    Route::get('/registrations/reports', [RegistrationController::class, 'reports'])->name('registrations.reports');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
})->name('api.health');
