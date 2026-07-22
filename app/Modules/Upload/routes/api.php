<?php

declare(strict_types=1);

use App\Modules\Upload\Http\Api\ChunkedUploadController;
use App\Modules\Upload\Http\Api\StudentAvatarUploadController;
use App\Modules\Upload\Http\Api\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Upload API Routes
|--------------------------------------------------------------------------
|
| Routes for handling image uploads with proper authentication,
| rate limiting, and access control.
|
*/

// Public routes (no authentication required)
Route::name('uploads.')->group(function () {
    // Serve private files with signature verification
    Route::get('/serve/{id}', [UploadController::class, 'serve'])
        ->name('serve')
        ->middleware(['throttle:uploads-serve']);

    // Get comprehensive upload configuration (public information)
    Route::get('/config', [UploadController::class, 'config'])
        ->name('config')
        ->middleware(['throttle:uploads-info']);

    // Get available upload contexts (public information)
    Route::get('/contexts', [UploadController::class, 'contexts'])
        ->name('contexts')
        ->middleware(['throttle:uploads-info']);
});

// Authenticated routes (API token required)
Route::middleware(['web', 'admin', 'auth'])->name('uploads.')->group(function () {
    // Upload operations with rate limiting
    Route::post('/', [UploadController::class, 'upload'])
        ->name('upload')
        ->middleware(['throttle:uploads-single']);

    Route::post('/multiple', [UploadController::class, 'uploadMultiple'])
        ->name('upload-multiple')
        ->middleware(['throttle:uploads-multiple']);

    // File validation endpoint
    Route::post('/validate', [UploadController::class, 'validateFile'])
        ->name('validate')
        ->middleware(['throttle:uploads-validate']);

    // Upload management
    Route::get('/', [UploadController::class, 'index'])
        ->name('index')
        ->middleware(['throttle:uploads-list']);

    Route::get('/{uploadRecord}', [UploadController::class, 'show'])
        ->name('show')
        ->middleware(['throttle:uploads-info']);

    Route::delete('/{uploadRecord}', [UploadController::class, 'destroy'])
        ->name('destroy')
        ->middleware(['throttle:uploads-delete']);

    // Chunked upload routes
    Route::prefix('chunked')->name('chunked.')->group(function () {
        Route::post('/initialize', [ChunkedUploadController::class, 'initialize'])
            ->name('initialize')
            ->middleware(['throttle:uploads-chunked-init']);

        Route::post('/chunk', [ChunkedUploadController::class, 'uploadChunk'])
            ->name('upload-chunk')
            ->middleware(['throttle:uploads-chunked-chunk']);

        Route::get('/{uploadId}/status', [ChunkedUploadController::class, 'status'])
            ->name('status')
            ->middleware(['throttle:uploads-info']);

        Route::delete('/{uploadId}', [ChunkedUploadController::class, 'cancel'])
            ->name('cancel')
            ->middleware(['throttle:uploads-delete']);

        Route::get('/statistics', [ChunkedUploadController::class, 'statistics'])
            ->name('statistics')
            ->middleware(['throttle:uploads-info']);
    });

    Route::post('/student-avatar/{studentId}', [StudentAvatarUploadController::class, 'upload'])
        ->name('student-avatar')
        // ->middleware(['throttle:uploads-single'])
        ->where('studentId', '[0-9]+');
});
