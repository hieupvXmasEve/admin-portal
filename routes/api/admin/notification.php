<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::name('notifications.')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
});
