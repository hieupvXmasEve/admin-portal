<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admissions\IngestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admissions CRM ingestion (server-to-server)
|--------------------------------------------------------------------------
|
| Mounted under /api/v1/admissions. The whole group is guarded by the
| ingestion middleware stack (auth:sanctum + IP allowlist + admissions:ingest
| ability + rate limit + audit log) wired at the mount point in routes/api.php.
| Ingestion never approves/rejects/revokes — those stay staff-only (ADR-0004).
|
*/

Route::get('/ping', [IngestionController::class, 'ping'])->name('ping');
