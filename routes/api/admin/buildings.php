<?php

use App\Http\Controllers\Api\Web\BuildingController;
use Illuminate\Support\Facades\Route;

Route::prefix('campuses/{campus}/buildings')->name('campuses.buildings.')->group(function () {
    Route::post('/', [BuildingController::class, 'store'])->name('store')->middleware('can:create_building');
    Route::put('/{building}', [BuildingController::class, 'update'])->name('update')->middleware('can:edit_building');
    Route::delete('/{building}', [BuildingController::class, 'destroy'])->name('destroy')->middleware('can:delete_building');
});
