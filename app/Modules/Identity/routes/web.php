<?php

use App\Modules\Identity\Http\Web\Admin\CampusSelectionController;
use App\Modules\Identity\Http\Web\Admin\LoginController;
use App\Modules\Identity\Http\Web\Admin\SocialAuthController;
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

        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});
