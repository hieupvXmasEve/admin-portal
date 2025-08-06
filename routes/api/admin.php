<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Web\StudentController as WebStudentController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\SyllabusController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\AdminScheduleController;
use App\Http\Controllers\Api\StudentApplicationController;

// Admin API routes (for internal use)
Route::middleware(['web'])->name('api.admin.')->group(function () {
    // Student Management
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/stats', [StudentController::class, 'stats'])->name('students.stats');
    Route::get('students/search', [WebStudentController::class, 'apiSearch'])->name('students.apiSearch');
    Route::get('students/{student}', [WebStudentController::class, 'apiShow'])->name('students.apiShow');

    // Student Applications
    Route::get('/student-applications', [StudentApplicationController::class, 'index'])->name('student-applications.index');
    Route::get('/student-applications/{studentApplication}', [StudentApplicationController::class, 'show'])->name('student-applications.show');
    Route::patch('/student-applications/{studentApplication}/status', [StudentApplicationController::class, 'updateStatus'])->name('student-applications.updateStatus');
    Route::post('/student-applications', [StudentApplicationController::class, 'store'])->name('student-applications.store');

    // Other admin API routes can go here if needed
});
