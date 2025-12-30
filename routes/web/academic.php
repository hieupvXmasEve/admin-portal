<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Admin\Academic\AcademicReportController;

Route::middleware(['auth', 'verified'])->prefix('academic')->name('academic.')->group(function () {
    Route::prefix('gpa')->name('gpa.')->group(function () {
        Route::get('/finalize', [GpaManagementController::class, 'index'])->name('finalize.index');
        Route::get('/preview', [GpaManagementController::class, 'preview'])->name('finalize.preview');
        Route::get('/check-eligibility', [GpaManagementController::class, 'checkEligibility'])->name('finalize.check-eligibility');
        Route::post('/finalize', [GpaManagementController::class, 'finalize'])->name('finalize.store');
    });

    Route::get('/report', [AcademicReportController::class, 'index'])->name('report.index');
});
