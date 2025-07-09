<?php

use App\Http\Controllers\Web\ProgramController;
use App\Constants\ProgramRoutes;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware(['auth', 'web'])->group(function () {

    // Programs routes with consistent naming
    Route::get('programs', [ProgramController::class, 'index'])
        ->middleware('can:view_program')
        ->name(ProgramRoutes::INDEX);

    Route::post('programs', [ProgramController::class, 'store'])
        ->middleware('can:create_program')
        ->name(ProgramRoutes::STORE);

    Route::get('programs/{program}', [ProgramController::class, 'show'])
        ->middleware('can:view_program')
        ->name(ProgramRoutes::SHOW);

    Route::put('programs/{program}', [ProgramController::class, 'update'])
        ->middleware('can:edit_program')
        ->name(ProgramRoutes::UPDATE);

    Route::delete('programs/{program}', [ProgramController::class, 'destroy'])
        ->middleware('can:delete_program')
        ->name(ProgramRoutes::DESTROY);
});

// API routes for AJAX calls
Route::middleware(['auth'])->group(function () {

    // Programs API routes
    Route::get('api/programs/search', [ProgramController::class, 'search'])
        ->name(ProgramRoutes::API_SEARCH);

    Route::delete('api/programs/bulk-delete', [ProgramController::class, 'bulkDelete'])
        ->name(ProgramRoutes::API_BULK_DELETE);
});
