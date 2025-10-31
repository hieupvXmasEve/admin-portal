<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SystemConfigController;
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

// System configuration endpoints
Route::get('/system-config', [SystemConfigController::class, 'index'])->name('api.system-config.index');
Route::put('/system-config', [SystemConfigController::class, 'update'])->name('api.system-config.update');
Route::post('/system-config/upload', [SystemConfigController::class, 'uploadFile'])->name('api.system-config.upload');
Route::get('/system-config/{key}', [SystemConfigController::class, 'show'])->name('api.system-config.show');

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
})->name('api.health');

// Upload routes
Route::prefix('uploads')->group(function () {
    require __DIR__ . '/api/uploads.php';
});

// require __DIR__.'/api/public.php';
require __DIR__ . '/api/admin.php';

// Module routes
require __DIR__ . '/api/modules.php';

// Versioned API routes
Route::prefix('v1/student')->name('v1.student.')->group(function () {
    require __DIR__ . '/api/v1/student.php';
    // Parent API routes
    Route::prefix('/parent')->name('v1.parent.')->group(function () {
        require __DIR__ . '/api/v1/parent.php';
    });
});
// Lecture
Route::prefix('v1/lecturer')->name('v1.lecturer.')->group(function () {
    require __DIR__ . '/api/v1/lecturer.php';
});
