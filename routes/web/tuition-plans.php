<?php

use App\Http\Controllers\TuitionPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Tuition plan management routes
    Route::prefix('tuition-plans')->name('tuition-plans.')->group(function () {
        Route::get('/', [TuitionPlanController::class, 'index'])
            ->middleware('can:view_tuition_plan')
            ->name('index');

        Route::get('create', [TuitionPlanController::class, 'create'])
            ->middleware('can:create_tuition_plan')
            ->name('create');

        Route::post('/', [TuitionPlanController::class, 'store'])
            ->middleware('can:create_tuition_plan')
            ->name('store');

        Route::get('{tuitionPlan}', [TuitionPlanController::class, 'show'])
            ->middleware('can:view_tuition_plan')
            ->name('show');

        Route::get('{tuitionPlan}/edit', [TuitionPlanController::class, 'edit'])
            ->middleware('can:edit_tuition_plan')
            ->name('edit');

        Route::put('{tuitionPlan}', [TuitionPlanController::class, 'update'])
            ->middleware('can:edit_tuition_plan')
            ->name('update');

        Route::delete('{tuitionPlan}', [TuitionPlanController::class, 'destroy'])
            ->middleware('can:delete_tuition_plan')
            ->name('destroy');
    });
});
