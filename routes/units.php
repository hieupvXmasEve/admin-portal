<?php

use App\Http\Controllers\UnitController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'units',
        controller: UnitController::class,
        module: 'units'
    );
});

// API routes for AJAX calls
Route::middleware(['auth'])->prefix('api/units')->name('api.units.')->group(function () {
    Route::get('search', [UnitController::class, 'search'])->name('search');
    Route::post('validate-code', [UnitController::class, 'validateCode'])->name('validate-code');
    Route::post('validate-prerequisite-expression', [UnitController::class, 'validatePrerequisiteExpression'])
        ->name('validate-prerequisite-expression');
    Route::delete('bulk-delete', [UnitController::class, 'bulkDelete'])->name('bulk-delete');
});
