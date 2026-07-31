<?php

use App\Http\Controllers\ScholarshipAdjustmentDossierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('scholarship-adjustments')->name('scholarship-adjustments.')->group(function () {
        Route::get('/', [ScholarshipAdjustmentDossierController::class, 'index'])
            ->middleware('can:view_scholarship_adjustment')
            ->name('index');

        Route::get('{dossier}', [ScholarshipAdjustmentDossierController::class, 'show'])
            ->middleware('can:view_scholarship_adjustment')
            ->name('show');

        Route::post('identify', [ScholarshipAdjustmentDossierController::class, 'identify'])
            ->middleware('can:manage_scholarship_adjustment_candidate')
            ->name('identify');

        Route::post('add-manually', [ScholarshipAdjustmentDossierController::class, 'addManually'])
            ->middleware('can:manage_scholarship_adjustment_candidate')
            ->name('add-manually');

        Route::post('{dossier}/interview/schedule', [ScholarshipAdjustmentDossierController::class, 'scheduleInterview'])
            ->middleware('can:manage_scholarship_interview')
            ->name('interview.schedule');

        Route::post('{dossier}/interview/complete', [ScholarshipAdjustmentDossierController::class, 'completeInterview'])
            ->middleware('can:manage_scholarship_interview')
            ->name('interview.complete');

        Route::post('{dossier}/decide', [ScholarshipAdjustmentDossierController::class, 'decide'])
            ->middleware('can:decide_scholarship_adjustment')
            ->name('decide');

        Route::post('{dossier}/approve', [ScholarshipAdjustmentDossierController::class, 'approve'])
            ->middleware('can:approve_scholarship_adjustment')
            ->name('approve');
    });
});
