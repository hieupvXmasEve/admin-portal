<?php

declare(strict_types=1);

use App\Modules\Institution\Http\Web\Admin\DepartmentController;
use App\Modules\Institution\Http\Web\Admin\DepartmentMemberController;
use App\Modules\Institution\Http\Web\CampusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'campus.selected'])->group(function (): void {
    Route::prefix('campuses')->name('campuses.')->group(function (): void {
        Route::get('/', [CampusController::class, 'index'])
            ->middleware('can:view_campus')
            ->name('index');
        Route::get('/create', [CampusController::class, 'create'])
            ->middleware('can:create_campus')
            ->name('create');
        Route::post('/', [CampusController::class, 'store'])
            ->middleware('can:create_campus')
            ->name('store');
        Route::get('/{campus}/edit', [CampusController::class, 'edit'])
            ->middleware('can:edit_campus')
            ->name('edit');
        Route::put('/{campus}', [CampusController::class, 'update'])
            ->middleware('can:edit_campus')
            ->name('update');
    });

    Route::prefix('admin/departments')->name('admin.departments.')->group(function (): void {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('/{department}/members', [DepartmentMemberController::class, 'index'])->name('members.index');
        Route::post('/{department}/members', [DepartmentMemberController::class, 'store'])->name('members.store');
        Route::put('/{department}/members/{member}', [DepartmentMemberController::class, 'update'])->name('members.update');
        Route::delete('/{department}/members/{member}', [DepartmentMemberController::class, 'destroy'])->name('members.destroy');
    });
});
