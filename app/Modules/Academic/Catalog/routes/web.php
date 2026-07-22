<?php

declare(strict_types=1);

use App\Constants\ProgramRoutes;
use App\Modules\Academic\Catalog\Http\Web\ProgramController;
use App\Modules\Academic\Catalog\Http\Web\SelectedAcademicPeriodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->group(function (): void {
    Route::post('semester-context', [SelectedAcademicPeriodController::class, 'update'])
        ->middleware('verified')
        ->name('semester-context.update');
    Route::post('finance/semester-context', [SelectedAcademicPeriodController::class, 'update'])
        ->name('finance.semester-context.update');

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
