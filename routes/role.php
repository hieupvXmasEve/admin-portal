<?php

use App\Http\Controllers\Web\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:view_role')
        ->name('role.index');
    Route::get('roles/create', [RoleController::class, 'create'])
        ->middleware('can:create_role')
        ->name('role.create');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('can:create_role')
        ->name('role.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->middleware('can:view_role')
        ->name('role.show');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('can:edit_role')
        ->name('role.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('can:edit_role')
        ->name('role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('can:delete_role')
        ->name('role.destroy');
});
