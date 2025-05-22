<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::group(['prefix' => 'users'], function () {
        Route::get('/', [UserController::class, 'index'])->middleware('can:view_user')->name('users');
        Route::get('/add', [UserController::class, 'add'])->middleware('can:add_user')->name('users.add');
        Route::post('/', [UserController::class, 'store'])->middleware('can:add_user')->name('users.store');
        Route::get('/edit/{user}', [UserController::class, 'edit'])->middleware('can:edit_user')->name('users.edit');
        Route::put('/{user}', [UserController::class, 'update'])->middleware('can:edit_user')->name('users.update');
    });
});
