<?php

use App\Constants\RoleRoutes;
use App\Http\Controllers\Web\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:view_role')
        ->name(RoleRoutes::INDEX);
    Route::get('roles/create', [RoleController::class, 'create'])
        ->middleware('can:create_role')
        ->name(RoleRoutes::CREATE);
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('can:create_role')
        ->name(RoleRoutes::STORE);
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->middleware('can:view_role')
        ->name(RoleRoutes::SHOW);
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('can:edit_role')
        ->name(RoleRoutes::EDIT);
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('can:edit_role')
        ->name(RoleRoutes::UPDATE);
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('can:delete_role')
        ->name(RoleRoutes::DESTROY);
});
