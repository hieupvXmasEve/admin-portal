<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::group(['prefix' => 'users'], function () {
        Route::get('/', [UserController::class, 'index'])->middleware('can:view_user')->name('users');
    });
});
