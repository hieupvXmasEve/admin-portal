<?php

use App\Http\Controllers\SyllabusController;
use Illuminate\Support\Facades\Route;

// syllabus routes nested under units
Route::middleware('auth')->group(function () {
    // syllabus management routes
    Route::prefix('units/{unit}/syllabus')->name('syllabus.')->group(function () {
        Route::get('/', [SyllabusController::class, 'index'])->name('index');
        Route::get('/create', [SyllabusController::class, 'create'])->name('create');
        Route::post('/', [SyllabusController::class, 'store'])->name('store');
        Route::get('/{syllabus}', [SyllabusController::class, 'show'])->name('show');
        Route::get('/{syllabus}/edit', [SyllabusController::class, 'edit'])->name('edit');
        Route::put('/{syllabus}', [SyllabusController::class, 'update'])->name('update');
        Route::delete('/{syllabus}', [SyllabusController::class, 'destroy'])->name('destroy');
        Route::patch('/{syllabus}/toggle-active', [SyllabusController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{syllabus}/clone', [SyllabusController::class, 'clone'])->name('clone');
    });
});
