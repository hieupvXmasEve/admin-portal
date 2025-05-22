<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::group(['prefix' => 'roles'], function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('can:view_role')->name('roles');
//        Route::get('/create', [RoleController::class, 'create'])->middleware('can:view_role')->name('roles');
    });
});
