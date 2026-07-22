<?php

declare(strict_types=1);

use App\Modules\Admissions\Http\Api\IngestionController;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admissions')->name('v1.admissions.')->middleware([
    'api',
    'auth:sanctum',
    'admissions.audit',
    'admissions.ingest',
    'throttle:'.AdmissionsIngestion::RATE_LIMITER,
])->group(function (): void {
    Route::get('/ping', [IngestionController::class, 'ping'])->name('ping');
    Route::post('/applications', [IngestionController::class, 'upsertApplication'])->name('applications.upsert');
});
