<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Parent\AuthController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware(['api.logging'])
        ->name('register');

    Route::post('/login', [AuthController::class, 'login'])
//        ->middleware(['student.api.rate:student-auth'])
        ->name('login');

    Route::post('/login/google', [AuthController::class, 'loginWithGoogle'])
//        ->middleware(['student.api.rate:student-auth'])
        ->name('login.google');

    Route::post('/refresh', [AuthController::class, 'refresh'])
//        ->middleware(['student.api.rate:student-auth'])
        ->name('refresh');
});

// Protected routes
Route::middleware([
    'auth:sanctum',
//    'api.rate:parent-api',
    'api.logging',
])->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });

    // Parent-specific endpoints
    Route::get('/children', [AuthController::class, 'getChildren'])->name('children');
});
