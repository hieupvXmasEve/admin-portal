<?php

use App\Constants\LectureRoutes;
use App\Http\Controllers\Web\LectureController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Lecturer resource routes
    Route::get('lectures', [LectureController::class, 'index'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::INDEX);

    Route::get('lectures/create', [LectureController::class, 'create'])
        ->middleware('can:create_lecturer')
        ->name(LectureRoutes::CREATE);

    Route::post('lectures', [LectureController::class, 'store'])
        ->middleware('can:create_lecturer')
        ->name(LectureRoutes::STORE);

    Route::get('lectures/{lecture}', [LectureController::class, 'show'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::SHOW);

    Route::get('lectures/{lecture}/edit', [LectureController::class, 'edit'])
        ->middleware('can:edit_lecturer')
        ->name(LectureRoutes::EDIT);

    Route::put('lectures/{lecture}', [LectureController::class, 'update'])
        ->middleware('can:edit_lecturer')
        ->name(LectureRoutes::UPDATE);

    Route::delete('lectures/{lecture}', [LectureController::class, 'destroy'])
        ->middleware('can:delete_lecturer')
        ->name(LectureRoutes::DESTROY);

    // API routes for dropdown/select usage and search
    Route::get('api/lectures/search', [LectureController::class, 'apiSearch'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::API_SEARCH);

    Route::get('api/lectures/statistics', [LectureController::class, 'statistics'])
        ->middleware('can:view_lecturer')
        ->name(LectureRoutes::API_STATISTICS);
});
