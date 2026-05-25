<?php

use App\Http\Controllers\Web\Admin\NotificationController;
use App\Modules\Notification\Http\Web\Admin\NotificationOpsController;
use App\Modules\Notification\Http\Web\Admin\NotificationTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin/notifications')->name('admin.notifications.')->group(function () {
    Route::get('/send', [NotificationController::class, 'sendForm'])->name('send-form');
    Route::post('/send', [NotificationController::class, 'send'])->name('send');

    // API for target search in the send form
    Route::get('/search-targets', [NotificationController::class, 'searchTargets'])->name('search-targets');

    // Notification Ops (monitoring)
    Route::prefix('ops')->name('ops.')->group(function () {
        Route::get('/outbox', [NotificationOpsController::class, 'outbox'])->name('outbox');
        Route::get('/outbox/{outbox}', [NotificationOpsController::class, 'outboxDetail'])->name('outbox.detail');
        Route::post('/outbox/{outbox}/retry', [NotificationOpsController::class, 'retryOutbox'])->name('outbox.retry');

        Route::get('/deliveries', [NotificationOpsController::class, 'deliveries'])->name('deliveries');
        Route::post('/deliveries/{delivery}/retry', [NotificationOpsController::class, 'retryDelivery'])->name('deliveries.retry');

        Route::get('/messages', [NotificationOpsController::class, 'messages'])->name('messages');
    });
});

Route::middleware(['auth', 'verified'])->prefix('admin/notification-templates')->name('admin.notification-templates.')->group(function () {
    Route::get('/', [NotificationTemplateController::class, 'index'])->name('index');
    Route::post('/', [NotificationTemplateController::class, 'store'])->name('store');
    Route::get('/{template}/edit', [NotificationTemplateController::class, 'edit'])->name('edit');
    Route::put('/{template}', [NotificationTemplateController::class, 'update'])->name('update');
});
