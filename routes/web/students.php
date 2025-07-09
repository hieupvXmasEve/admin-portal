<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\StudentController;
use App\Constants\StudentRoutes;

/*
|--------------------------------------------------------------------------
| Student Management Routes
|--------------------------------------------------------------------------
|
| These routes handle student management functionality for admin users.
| All routes require authentication and appropriate permissions.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // Student management routes
    Route::get('students', [StudentController::class, 'index'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::INDEX);

    Route::get('students/create', [StudentController::class, 'create'])
        ->middleware('can:create_student')
        ->name(StudentRoutes::CREATE);

    Route::post('students', [StudentController::class, 'store'])
        ->middleware('can:create_student')
        ->name(StudentRoutes::STORE);

    Route::get('students/{student}', [StudentController::class, 'show'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::SHOW);

    Route::get('students/{student}/edit', [StudentController::class, 'edit'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::EDIT);

    Route::put('students/{student}', [StudentController::class, 'update'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::UPDATE);

    Route::delete('students/{student}', [StudentController::class, 'destroy'])
        ->middleware('can:delete_student')
        ->name(StudentRoutes::DESTROY);

    // Additional student management actions
    Route::post('students/{student}/assign-program', [StudentController::class, 'assignProgram'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::ASSIGN_PROGRAM);

    Route::post('students/{student}/update-status', [StudentController::class, 'updateStatus'])
        ->middleware('can:edit_student')
        ->name(StudentRoutes::UPDATE_STATUS);

    // AJAX endpoints for student management (for web interface)
    Route::get('ajax/students/search', [StudentController::class, 'apiSearch'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::AJAX_SEARCH);

    Route::get('ajax/students/{student}', [StudentController::class, 'apiShow'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::AJAX_SHOW);

    Route::get('ajax/students/specializations', [StudentController::class, 'getSpecializations'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::AJAX_SPECIALIZATIONS);

    Route::get('ajax/students/curriculum-versions', [StudentController::class, 'getCurriculumVersions'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::AJAX_CURRICULUM_VERSIONS);

    Route::post('ajax/students/by-ids', [StudentController::class, 'getByIds'])
        ->middleware('can:view_student')
        ->name(StudentRoutes::AJAX_BY_IDS);
});
