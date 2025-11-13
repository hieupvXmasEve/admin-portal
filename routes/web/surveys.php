<?php

use App\Http\Controllers\Web\SurveySettingsController;
use App\Http\Controllers\Web\SurveyManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'campus.selected'])->group(function () {
    // Settings
    Route::get('/surveys/settings', [SurveySettingsController::class, 'index'])
        ->middleware('can:view_survey')
        ->name('surveys.settings.index');
    Route::post('/surveys/settings', [SurveySettingsController::class, 'update'])
        ->middleware('can:edit_survey')
        ->name('surveys.settings.update');

    // Survey Management
    Route::get('/surveys', [SurveyManagementController::class, 'index'])
        ->middleware('can:view_survey')
        ->name('surveys.index');
    Route::get('/surveys/{survey}', [SurveyManagementController::class, 'show'])
        ->middleware('can:view_survey')
        ->name('surveys.show');
});
