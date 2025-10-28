<?php

declare(strict_types=1);

use App\Http\Controllers\Web\FailedStudentsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'campus.selected'])->group(function () {
    Route::get('failed-students', [FailedStudentsController::class, 'index'])
        ->middleware('can:view_attendance')
        ->name('failed-students.index');

    Route::get('failed-students/export', [FailedStudentsController::class, 'export'])
        ->middleware('can:view_attendance')
        ->name('failed-students.export');
});
