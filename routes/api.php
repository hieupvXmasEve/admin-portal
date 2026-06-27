<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SystemConfigController;
use App\Modules\Finance\Dng\Http\Controllers\DngWebhookController;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use Illuminate\Support\Facades\Broadcast;
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

// Broadcasting routes for API-based authentication (Laravel Echo)
Broadcast::routes(['middleware' => ['auth:sanctum', 'student.api.auth']]);

// System configuration endpoints
Route::get('/system-config', [SystemConfigController::class, 'index'])->name('api.system-config.index');
Route::put('/system-config', [SystemConfigController::class, 'update'])->name('api.system-config.update');
Route::post('/system-config/upload', [SystemConfigController::class, 'uploadFile'])->name('api.system-config.upload');
Route::get('/system-config/{key}', [SystemConfigController::class, 'show'])->name('api.system-config.show');

// DNG payment webhook (public, no auth — checksum verified in controller)
Route::post('/webhooks/dng/payment', DngWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.webhooks.dng.payment');

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
    require __DIR__.'/api/uploads.php';
});

// require __DIR__.'/api/public.php';
require __DIR__.'/api/admin.php';

// Module routes
require __DIR__.'/api/modules.php';

// Versioned API routes
Route::prefix('v1/student')->name('v1.student.')->group(function () {
    require __DIR__.'/api/v1/student.php';
});
// Lecture
Route::prefix('v1/lecturer')->name('v1.lecturer.')->group(function () {
    require __DIR__.'/api/v1/lecturer.php';
});

// Admissions CRM ingestion (server-to-server). Hardened group: Sanctum token +
// IP allowlist + admissions:ingest ability + rate limit + per-call audit log.
Route::prefix('v1/admissions')
    ->name('v1.admissions.')
    ->middleware([
        // Audit runs right after authentication and outside the authorization
        // check, so both successful ingestion calls and authenticated-but-denied
        // attempts (bad IP / missing ability) are recorded with their causer.
        'auth:sanctum',
        'admissions.audit',
        'admissions.ingest',
        'throttle:'.AdmissionsIngestion::RATE_LIMITER,
    ])
    ->group(function () {
        require __DIR__.'/api/v1/admissions.php';
    });
