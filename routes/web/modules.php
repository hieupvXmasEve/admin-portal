<?php

declare(strict_types=1);

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified',])->prefix('admin')->name('admin.')->group(function () {
    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::get('modules/create', [ModuleController::class, 'create'])->name('modules.create');
    Route::post('modules', [ModuleController::class, 'store'])->name('modules.store');
    Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
    Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
    Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');
    Route::post('modules/{module}/sync-units', [ModuleController::class, 'syncUnits'])->name('modules.sync-units');
});
