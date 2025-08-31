<?php

use App\Constants\ProgramRoutes;
use App\Http\Controllers\Web\ProgramController;
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
