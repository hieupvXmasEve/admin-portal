<?php

use App\Modules\Identity\Http\Web\Admin\CampusSelectionController;
use App\Modules\Identity\Http\Web\Admin\LoginController;
use App\Modules\Identity\Http\Web\Admin\SocialAuthController;
use App\Modules\Identity\Http\Web\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('auth/google/redirect', [SocialAuthController::class, 'redirect'])->name('google.login');
        Route::get('auth/google/callback', [SocialAuthController::class, 'callback']);

        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store']);
    });

    // Auth routes
    Route::middleware('auth')->group(function () {
        Route::get('select-campus', [CampusSelectionController::class, 'index'])->name('select-campus.index');
        Route::post('select-campus/set-current', [CampusSelectionController::class, 'setCurrentCampus'])->name('select-campus.set-current');
        Route::post('select-campus/change', [CampusSelectionController::class, 'changeCampus'])->name('select-campus.change');

        // User management routes
        Route::middleware('can:view_user')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('identity.users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('identity.users.create')->middleware('can:create_user');
            Route::post('users', [UserController::class, 'store'])->name('identity.users.store')->middleware('can:create_user');
            Route::get('users/{user}', [UserController::class, 'show'])->name('identity.users.show');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('identity.users.edit')->middleware('can:edit_user');
            Route::put('users/{user}', [UserController::class, 'update'])->name('identity.users.update')->middleware('can:edit_user');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('identity.users.destroy')->middleware('can:delete_user');
        });

        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});
