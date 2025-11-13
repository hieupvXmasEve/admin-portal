<?php

use App\Http\Controllers\Web\SurveySettingsController;
use App\Http\Controllers\Web\SurveyManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'campus.selected'])->group(function () {
    // Settings
    Route::get('/surveys/settings', [SurveySettingsController::class, 'index'])
        ->name('surveys.settings.index');
    Route::post('/surveys/settings', [SurveySettingsController::class, 'update'])
        ->name('surveys.settings.update');

    // Survey Management
    Route::get('/surveys', [SurveyManagementController::class, 'index'])
        ->name('surveys.index');
    Route::get('/surveys/{survey}', [SurveyManagementController::class, 'show'])
        ->name('surveys.show');
});
