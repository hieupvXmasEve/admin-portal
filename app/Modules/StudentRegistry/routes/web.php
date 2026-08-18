<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Modules\StudentRegistry\Http\Web\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Registry Web Routes
|--------------------------------------------------------------------------
|
| Student directory CRUD and the photo-capture screen. These live here rather
| than in the Academic route file so the Academic context does not reference a
| StudentRegistry controller.
|
*/

Route::middleware(['auth', 'verified', 'campus.selected'])->group(function () {
    Route::prefix('students')->group(function () {

        Route::get('/', [StudentController::class, 'index'])
            ->middleware('can:view_student')
            ->name(StudentRoutes::INDEX);

        Route::get('export', [StudentController::class, 'export'])
            ->middleware('can:view_student')
            ->name(StudentRoutes::EXPORT);

        Route::get('create', [StudentController::class, 'create'])
            ->middleware('can:create_student')
            ->name(StudentRoutes::CREATE);

        Route::post('students', [StudentController::class, 'store'])
            ->middleware('can:create_student')
            ->name(StudentRoutes::STORE);

        Route::get('{student}/edit', [StudentController::class, 'edit'])
            ->middleware('can:edit_student')
            ->name(StudentRoutes::EDIT);

        Route::put('{student}', [StudentController::class, 'update'])
            ->middleware('can:edit_student')
            ->name(StudentRoutes::UPDATE);

        Route::delete('{student}', [StudentController::class, 'destroy'])
            ->middleware('can:delete_student')
            ->name(StudentRoutes::DESTROY);
    });

    Route::get('students/{student}/photo-capture', [StudentController::class, 'photoCapture'])
        ->middleware('can:edit_student')
        ->name('students.photo-capture');
});
