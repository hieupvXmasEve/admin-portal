<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Lecturer API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for lecturer-related functionality.
| These routes are loaded by the main API routes file.
|
*/

// Protected lecturer routes
Route::name('api.lecturer.')->middleware(['auth:sanctum', 'ability:lecturer'])->group(function () {
    Route::post('/auth/lecturer/logout', [AuthController::class, 'lecturerLogout'])->name('auth.logout');
    Route::get('/auth/lecturer/me', [AuthController::class, 'lecturerMe'])->name('auth.me');
});
