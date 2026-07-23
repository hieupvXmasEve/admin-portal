<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Http\Controllers\DngWebhookController;
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
