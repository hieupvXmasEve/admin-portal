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

// Admin API routes (for internal use)
Route::middleware(['auth'])->name('api.admin.')->group(function () {
    // Student Management
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/stats', [StudentController::class, 'stats'])->name('students.stats');


    // Other admin API routes can go here if needed
});
