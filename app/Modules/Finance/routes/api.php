<?php

use App\Modules\Finance\Dng\Http\Controllers\BatchDngApiController;
use App\Modules\Finance\Dng\Http\Controllers\DngPaymentController;
use App\Modules\Finance\Http\Api\Admin\BillingOperationsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance Module API Routes
|--------------------------------------------------------------------------
|
| Student-facing finance API endpoints for balance inquiry,
| charge listing, and payment history.
|
*/

/*
|--------------------------------------------------------------------------
| Admin Finance API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1/finance/operations')
    ->middleware(['web', 'auth']) // Assuming sanctum for admin API access
    ->name('api.finance.operations.')
    ->group(function () {
        Route::post('/preview-charges', [BillingOperationsController::class, 'previewCharges'])->name('preview-charges');
        Route::post('/export-preview-charges', [BillingOperationsController::class, 'exportPreviewCharges'])->name('export-preview-charges');
        Route::post('/run-generate', [BillingOperationsController::class, 'runGenerate'])->name('run-generate');
        Route::post('/exceptions/{exceptionId}/fix', [BillingOperationsController::class, 'fixException'])->name('fix-exception');
        Route::post('/send-reminders', [BillingOperationsController::class, 'sendReminders'])->name('send-reminders');
        Route::post('/send-parent-reminders', [BillingOperationsController::class, 'sendParentReminders'])->name('send-parent-reminders');
        Route::post('/send-due-item-reminders', [BillingOperationsController::class, 'sendDueItemReminders'])->name('send-due-item-reminders');
        Route::post('/send-due-item-parent-reminders', [BillingOperationsController::class, 'sendDueItemParentReminders'])->name('send-due-item-parent-reminders');
    });

/*
|--------------------------------------------------------------------------
| DNG Payment Gateway Routes
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1/finance/dng')
    ->middleware(['web', 'auth'])
    ->name('api.finance.dng.')
    ->group(function () {
        Route::post('/payment-requests', [DngPaymentController::class, 'store'])->name('payment-requests.store');
        Route::post('/batch', [BatchDngApiController::class, 'store'])->name('batch.store');
    });
