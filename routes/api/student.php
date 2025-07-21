<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

// Protected API routes for students
Route::name('api.')->middleware(['auth:sanctum', 'student'])->group(function () {
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
