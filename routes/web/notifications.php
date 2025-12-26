<?php

use App\Http\Controllers\Web\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin/notifications')->name('admin.notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/send', [NotificationController::class, 'sendForm'])->name('send-form');
    Route::post('/send', [NotificationController::class, 'send'])->name('send');
    
    // API for student search in the send form
    Route::get('/search-students', [NotificationController::class, 'searchStudents'])->name('search-students');
});
