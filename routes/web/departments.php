<?php

use App\Http\Controllers\Web\Admin\DepartmentController;
use App\Http\Controllers\Web\Admin\DepartmentMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin/departments')->name('admin.departments.')->group(function () {
    Route::get('/', [DepartmentController::class, 'index'])->name('index');
    Route::get('/{department}/members', [DepartmentMemberController::class, 'index'])->name('members.index');
    Route::post('/{department}/members', [DepartmentMemberController::class, 'store'])->name('members.store');
    Route::put('/{department}/members/{member}', [DepartmentMemberController::class, 'update'])->name('members.update');
    Route::delete('/{department}/members/{member}', [DepartmentMemberController::class, 'destroy'])->name('members.destroy');
});
