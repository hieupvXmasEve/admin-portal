<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ModuleProgressController;
use Illuminate\Support\Facades\Route;

// Student module progress (requires authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/module-progress', [ModuleProgressController::class, 'index'])
        ->name('api.module-progress.index');
});
