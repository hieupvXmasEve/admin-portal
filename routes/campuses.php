<?php

use App\Http\Controllers\Web\BuildingController;
use App\Http\Controllers\Web\CampusController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Campus resource routes
    Route::get('campuses', [CampusController::class, 'index'])
        ->middleware('can:view_campus')
        ->name('campuses.index');

    Route::get('campuses/create', [CampusController::class, 'create'])
        ->middleware('can:create_campus')
        ->name('campuses.create');

    Route::post('campuses', [CampusController::class, 'store'])
        ->middleware('can:create_campus')
        ->name('campuses.store');

    Route::get('campuses/{campus}', [CampusController::class, 'show'])
        ->middleware('can:view_campus')
        ->name('campuses.show');

    Route::get('campuses/{campus}/edit', [CampusController::class, 'edit'])
        ->middleware('can:edit_campus')
        ->name('campuses.edit');

    Route::put('campuses/{campus}', [CampusController::class, 'update'])
        ->middleware('can:edit_campus')
        ->name('campuses.update');

    Route::delete('campuses/{campus}', [CampusController::class, 'destroy'])
        ->middleware('can:delete_campus')
        ->name('campuses.destroy');

    // Nested building routes under campuses
    Route::prefix('campuses/{campus}')->name('campuses.')->group(function () {
        Route::get('buildings/create', [BuildingController::class, 'create'])
            ->middleware('can:create_building')
            ->name('buildings.create');

        Route::post('buildings', [BuildingController::class, 'store'])
            ->middleware('can:create_building')
            ->name('buildings.store');

        Route::get('buildings/{building}/edit', [BuildingController::class, 'edit'])
            ->middleware('can:edit_building')
            ->name('buildings.edit');

        Route::put('buildings/{building}', [BuildingController::class, 'update'])
            ->middleware('can:edit_building')
            ->name('buildings.update');

        Route::delete('buildings/{building}', [BuildingController::class, 'destroy'])
            ->middleware('can:delete_building')
            ->name('buildings.destroy');
    });

    // API routes for dropdown/select usage
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('campuses', [CampusController::class, 'api'])
            ->middleware('can:view_campus')
            ->name('campuses');
    });
});
