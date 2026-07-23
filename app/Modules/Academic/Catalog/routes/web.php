<?php

declare(strict_types=1);

use App\Constants\ProgramRoutes;
use App\Constants\SemesterRoutes;
use App\Modules\Academic\Catalog\Http\Web\AcademicPeriodController;
use App\Modules\Academic\Catalog\Http\Web\ProgramController;
use App\Modules\Academic\Catalog\Http\Web\SelectedAcademicPeriodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web'])->group(function (): void {
    Route::get('semesters', [AcademicPeriodController::class, 'index'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::INDEX);
    Route::post('semesters', [AcademicPeriodController::class, 'store'])
        ->middleware('can:create_semester')
        ->name(SemesterRoutes::STORE);
    Route::put('semesters/{semester}', [AcademicPeriodController::class, 'update'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::UPDATE);
    Route::delete('semesters/{semester}', [AcademicPeriodController::class, 'destroy'])
        ->middleware('can:delete_semester')
        ->name(SemesterRoutes::DESTROY);
    Route::put('api/semesters/{semester}', [AcademicPeriodController::class, 'apiUpdate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::API_UPDATE);
    Route::post('api/semesters/{semester}/activate', [AcademicPeriodController::class, 'activate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::ACTIVATE);
    Route::post('api/semesters/{semester}/deactivate', [AcademicPeriodController::class, 'deactivate'])
        ->middleware('can:edit_semester')
        ->name(SemesterRoutes::DEACTIVATE);
    Route::get('api/semesters/activation-statuses', [AcademicPeriodController::class, 'activationStatuses'])
        ->middleware('can:view_semester')
        ->name(SemesterRoutes::ACTIVATION_STATUSES);

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
