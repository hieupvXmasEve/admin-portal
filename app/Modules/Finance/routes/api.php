<?php

use App\Modules\Finance\Http\Api\Admin\BatchStudioPreviewController;
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
        // Non-academic charge generation (CSV upload → bulk charge creation).
        Route::post('/generate-non-academic-charges', [BillingOperationsController::class, 'generateNonAcademic'])->name('generate-non-academic-charges');

        Route::post('/exceptions/{exceptionId}/fix', [BillingOperationsController::class, 'fixException'])
            ->middleware('can:view_finance_operations_exceptions')
            ->name('fix-exception');
        Route::post('/send-reminders', [BillingOperationsController::class, 'sendReminders'])->name('send-reminders');
        Route::post('/send-parent-reminders', [BillingOperationsController::class, 'sendParentReminders'])->name('send-parent-reminders');
        Route::post('/send-due-item-reminders', [BillingOperationsController::class, 'sendDueItemReminders'])->name('send-due-item-reminders');
        Route::post('/send-due-item-parent-reminders', [BillingOperationsController::class, 'sendDueItemParentReminders'])->name('send-due-item-parent-reminders');
        Route::post('/send-tuition-notices', [BillingOperationsController::class, 'sendTuitionNotices'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('send-tuition-notices');
    });

Route::prefix('api/v1/finance/batch-studio')
    ->middleware(['web', 'auth'])
    ->name('finance.batch-studio.')
    ->group(function () {
        Route::post('/charges/preview', [BatchStudioPreviewController::class, 'previewCharges'])
            ->name('charges.preview');
        Route::post('/dng/preview', [BatchStudioPreviewController::class, 'previewDng'])
            ->middleware('can:create_finance_payments')
            ->name('dng.preview');
    });
