<?php

use App\Constants\ClubRoutes;
use App\Http\Controllers\Web\ClubController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Club resource routes
    Route::get('clubs', [ClubController::class, 'index'])
        ->middleware('can:view_clubs')
        ->name(ClubRoutes::INDEX);

    Route::get('clubs/create', [ClubController::class, 'create'])
        ->middleware('can:create_clubs')
        ->name(ClubRoutes::CREATE);

    Route::post('clubs', [ClubController::class, 'store'])
        ->middleware('can:create_clubs')
        ->name(ClubRoutes::STORE);

    Route::get('clubs/{club}', [ClubController::class, 'show'])
        ->middleware('can:view_clubs')
        ->name(ClubRoutes::SHOW);

    Route::get('clubs/{club}/edit', [ClubController::class, 'edit'])
        ->middleware('can:edit_clubs')
        ->name(ClubRoutes::EDIT);

    Route::put('clubs/{club}', [ClubController::class, 'update'])
        ->middleware('can:edit_clubs')
        ->name(ClubRoutes::UPDATE);

    Route::delete('clubs/{club}', [ClubController::class, 'destroy'])
        ->middleware('can:delete_clubs')
        ->name(ClubRoutes::DESTROY);

    // Custom club management routes
    Route::post('clubs/{club}/assign-president', [ClubController::class, 'assignPresident'])
        ->middleware('can:edit_clubs')
        ->name(ClubRoutes::ASSIGN_PRESIDENT);

    // API routes for dropdown/select usage
    Route::get('api/clubs/{club}/students-for-assignment', [ClubController::class, 'studentsForAssignment'])
        ->middleware('can:edit_clubs')
        ->name(ClubRoutes::API_STUDENTS_FOR_ASSIGNMENT);

    Route::get('api/clubs/students-for-campus', [ClubController::class, 'studentsForCampus'])
        ->middleware('can:create_clubs')
        ->name(ClubRoutes::API_STUDENTS_FOR_CAMPUS);
});
