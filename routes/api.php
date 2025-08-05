<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::name('api.')->group(function () {
    Route::post('v1/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('v1/auth/login/google', [AuthController::class, 'loginWithGoogle'])->name('auth.login.google');
    Route::post('v1/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('v1/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('v1/auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
})->name('api.health');

// require __DIR__.'/api/public.php';
require __DIR__.'/api/admin.php';

// Versioned API routes
Route::prefix('v1/student')->name('v1.student.')->group(function () {
    require __DIR__.'/api/v1/student.php';
});
// Lecture
Route::prefix('v1/lecturer')->name('v1.lecturer.')->group(function () {
    require __DIR__.'/api/v1/lecturer.php';
});
