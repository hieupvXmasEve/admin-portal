<?php

declare(strict_types=1);

use App\Http\Controllers\CourseOfferingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Course Offerings resource routes
    Route::prefix('course-offerings')->name('course-offerings.')->group(function () {
        Route::get('/', [CourseOfferingController::class, 'index'])
            ->middleware('can:view_course')
            ->name('index');

        Route::get('/create', [CourseOfferingController::class, 'create'])
            ->middleware('can:create_course')
            ->name('create');

        Route::get('/{courseOffering}/edit', [CourseOfferingController::class, 'edit'])
            ->middleware('can:edit_course')
            ->name('edit');

        Route::post('/', [CourseOfferingController::class, 'store'])
            ->middleware('can:create_course')
            ->name('store');

        Route::get('/{courseOffering}', [CourseOfferingController::class, 'show'])
            ->middleware('can:view_course')
            ->name('show');

        Route::put('/{courseOffering}', [CourseOfferingController::class, 'update'])
            ->middleware('can:edit_course')
            ->name('update');

        Route::delete('/{courseOffering}', [CourseOfferingController::class, 'destroy'])
            ->middleware('can:delete_course')
            ->name('destroy');

        Route::patch('/{courseOffering}/toggle-status', [CourseOfferingController::class, 'toggleStatus'])
            ->middleware('can:edit_course')
            ->name('toggle-status');
    });

    // API routes for bulk operations and statistics
    Route::delete('/api/course-offerings/bulk-delete', [CourseOfferingController::class, 'bulkDelete'])
        ->middleware('can:delete_course')
        ->name('api.course-offerings.bulk-delete');

    Route::get('/api/course-offerings/statistics', [CourseOfferingController::class, 'statistics'])
        ->middleware('can:view_course')
        ->name('api.course-offerings.statistics');
});
