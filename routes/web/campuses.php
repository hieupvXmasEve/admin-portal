<?php

use App\Constants\CampusRoutes;
use App\Http\Controllers\Web\CampusController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Campus resource routes
    Route::get('campuses', [CampusController::class, 'index'])
        ->middleware('can:view_campus')
        ->name(CampusRoutes::INDEX);

    Route::get('campuses/create', [CampusController::class, 'create'])
        ->middleware('can:create_campus')
        ->name(CampusRoutes::CREATE);

    Route::post('campuses', [CampusController::class, 'store'])
        ->middleware('can:create_campus')
        ->name(CampusRoutes::STORE);

    Route::get('campuses/{campus}', [CampusController::class, 'show'])
        ->middleware('can:view_campus')
        ->name(CampusRoutes::SHOW);

    Route::get('campuses/{campus}/edit', [CampusController::class, 'edit'])
        ->middleware('can:edit_campus')
        ->name(CampusRoutes::EDIT);

    Route::put('campuses/{campus}', [CampusController::class, 'update'])
        ->middleware('can:edit_campus')
        ->name(CampusRoutes::UPDATE);

    Route::delete('campuses/{campus}', [CampusController::class, 'destroy'])
        ->middleware('can:delete_campus')
        ->name(CampusRoutes::DESTROY);
});
