<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;

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
    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])
            ->middleware('can:view_student')
            ->name('index');

        Route::get('/create', [StudentController::class, 'create'])
            ->middleware('can:create_student')
            ->name('create');

        Route::post('/', [StudentController::class, 'store'])
            ->middleware('can:create_student')
            ->name('store');

        Route::get('/{student}', [StudentController::class, 'show'])
            ->middleware('can:view_student')
            ->name('show');

        Route::get('/{student}/edit', [StudentController::class, 'edit'])
            ->middleware('can:edit_student')
            ->name('edit');

        Route::put('/{student}', [StudentController::class, 'update'])
            ->middleware('can:edit_student')
            ->name('update');

        Route::delete('/{student}', [StudentController::class, 'destroy'])
            ->middleware('can:delete_student')
            ->name('destroy');

        // Additional student management actions
        Route::post('/{student}/assign-program', [StudentController::class, 'assignProgram'])
            ->middleware('can:edit_student')
            ->name('assign-program');

        Route::post('/{student}/update-status', [StudentController::class, 'updateStatus'])
            ->middleware('can:edit_student')
            ->name('update-status');
    });

    // AJAX endpoints for student management
    Route::prefix('api/students')->name('api.students.')->group(function () {
        Route::get('/search', [StudentController::class, 'apiSearch'])
            ->middleware('can:view_student')
            ->name('search');

        Route::get('/{student}', [StudentController::class, 'apiShow'])
            ->middleware('can:view_student')
            ->name('show');

        Route::get('/specializations', [StudentController::class, 'getSpecializations'])
            ->middleware('can:view_student')
            ->name('specializations');

        Route::get('/curriculum-versions', [StudentController::class, 'getCurriculumVersions'])
            ->middleware('can:view_student')
            ->name('curriculum-versions');
    });
});
