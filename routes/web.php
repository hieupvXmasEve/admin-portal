<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SelectCampus;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified']);

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware(['auth'])->group(callback: function () {
    Route::get('select-campus', [SelectCampus::class, 'index'])->name('select-campus.index');
    Route::post('select-campus/set-current', [SelectCampus::class, 'setCurrentCampus'])->name('select-campus.set-current');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/user.php';
require __DIR__ . '/role.php';
require __DIR__ . '/semester.php';
require __DIR__ . '/units.php';
require __DIR__ . '/syllabus.php';
require __DIR__ . '/programs.php';
require __DIR__ . '/specializations.php';
require __DIR__ . '/curriculum.php';
