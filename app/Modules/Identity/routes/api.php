<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Api\Lecturer\LecturerAuthController;
use App\Modules\Identity\Http\Api\Parent\ParentAuthController;
use App\Modules\Identity\Http\Api\Parent\ParentContextController;
use App\Modules\Identity\Http\Api\Student\StudentAuthController;
use App\Modules\Identity\Http\Api\Student\StudentContextController;
use Illuminate\Support\Facades\Route;

/**
 * Student API Routes
 */
Route::prefix('v1/student')->name('api.student.')->group(function () {
    // Guest routes
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [StudentAuthController::class, 'login'])->name('login');
        Route::post('/login/google', [StudentAuthController::class, 'loginWithGoogle'])->name('login.google');
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'api.logging', 'api.actor:student_or_parent'])->group(function () {
        Route::middleware(['either:parent.student.access,student.api.auth'])->group(function () {
            Route::prefix('auth')->name('auth.')->group(function () {
                Route::post('/logout', [StudentAuthController::class, 'logout'])->name('logout');
                Route::post('/refresh', [StudentAuthController::class, 'refresh'])->name('refresh');
            });

            Route::get('/context', [StudentContextController::class, 'index'])->name('context');
        });
    });

    /**
     * Parent API Routes
     */
    Route::prefix('parent')->name('parent.')->group(function () {
        // Guest routes
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/login', [ParentAuthController::class, 'login'])->name('login');
            Route::post('/login/google', [ParentAuthController::class, 'loginWithGoogle'])->name('login.google');
        });

        // Protected routes
        Route::middleware(['auth:sanctum', 'api.logging', 'api.actor:parent'])->group(function () {
            Route::prefix('auth')->name('auth.')->group(function () {
                Route::post('/logout', [ParentAuthController::class, 'logout'])->name('logout');
                Route::post('/refresh', [ParentAuthController::class, 'refresh'])->name('refresh');
            });

            Route::get('/context', [ParentContextController::class, 'index'])->name('context');
        });
    });
});

/**
 * Lecturer API Routes
 */
Route::prefix('v1/lecturer')->name('api.lecturer.')->group(function () {
    // Guest routes
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [LecturerAuthController::class, 'login'])->name('login');
        Route::post('/login/google', [LecturerAuthController::class, 'loginWithGoogle'])->name('login.google');
        Route::get('/check-science', [LecturerAuthController::class, 'checkScience'])->name('check-science');
    });

    // Protected routes
    Route::middleware([
        'auth:sanctum',
        'api.actor:lecturer',
        'lecturer.api.auth',
        'api.logging',
    ])->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/refresh', [LecturerAuthController::class, 'refresh'])->name('refresh');
            Route::post('/logout', [LecturerAuthController::class, 'logout'])->name('logout');
            Route::get('/me', [LecturerAuthController::class, 'me'])->name('me');
        });
    });
});
