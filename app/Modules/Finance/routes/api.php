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

        // @deprecated — bulk EGC flow. Retained per Q9 decision; remove after confirming no consumers.
        Route::post('/preview-charges', [BillingOperationsController::class, 'previewCharges'])->name('preview-charges');
        Route::post('/export-preview-charges', [BillingOperationsController::class, 'exportPreviewCharges'])->name('export-preview-charges');
        Route::post('/run-generate', [BillingOperationsController::class, 'runGenerate'])->name('run-generate');
        Route::post('/exceptions/{exceptionId}/fix', [BillingOperationsController::class, 'fixException'])
            ->middleware('can:view_finance_operations_exceptions')
            ->name('fix-exception');
        Route::post('/send-reminders', [BillingOperationsController::class, 'sendReminders'])->name('send-reminders');
        Route::post('/send-parent-reminders', [BillingOperationsController::class, 'sendParentReminders'])->name('send-parent-reminders');
        Route::post('/send-due-item-reminders', [BillingOperationsController::class, 'sendDueItemReminders'])->name('send-due-item-reminders');
        Route::post('/send-due-item-parent-reminders', [BillingOperationsController::class, 'sendDueItemParentReminders'])->name('send-due-item-parent-reminders');
    });

Route::prefix('api/v1/finance/batch-studio')
    ->middleware(['web', 'auth'])
    ->name('finance.batch-studio.')
    ->group(function () {
        Route::post('/charges/preview', [BatchStudioPreviewController::class, 'previewCharges'])
            ->middleware('can:create_finance_charges')
            ->name('charges.preview');
        Route::post('/dng/preview', [BatchStudioPreviewController::class, 'previewDng'])
            ->middleware('can:create_finance_payments')
            ->name('dng.preview');
        Route::post('/reminders/preview', [BatchStudioPreviewController::class, 'previewReminders'])
            ->middleware('can:view_finance_operations_due_calendar')
            ->name('reminders.preview');
    });
