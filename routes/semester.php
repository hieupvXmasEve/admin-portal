<?php

use App\Http\Controllers\SemesterController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'semesters',
        controller: SemesterController::class,
        module: 'semesters'
    );

    // API routes for semester activation (using edit permission for safety)
    Route::post('semesters/{semester}/activate', [SemesterController::class, 'activate'])
        ->middleware('can:edit_semester')
        ->name('semesters.activate');
    Route::post('semesters/{semester}/deactivate', [SemesterController::class, 'deactivate'])
        ->middleware('can:edit_semester')
        ->name('semesters.deactivate');
    Route::get('semesters/activation-statuses', [SemesterController::class, 'activationStatuses'])
        ->middleware('can:view_semester')
        ->name('semesters.activation-statuses');
});
