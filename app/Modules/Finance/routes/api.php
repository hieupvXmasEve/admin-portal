<?php

use App\Modules\Finance\Http\Api\Admin\BillingOperationsController;
use App\Modules\Finance\Http\Api\Student\StudentFinanceController;
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

Route::prefix('api/v1/student/finance')
    ->middleware(['web', 'auth'])
    ->name('api.student.finance.')
    ->group(function () {
        // Balance summary
        Route::get('/balance', [StudentFinanceController::class, 'balance'])->name('balance');
        
        // Charges list
        Route::get('/charges', [StudentFinanceController::class, 'charges'])->name('charges');
        Route::get('/charges/{chargeId}', [StudentFinanceController::class, 'chargeDetail'])->name('charges.show');
        
        // Payment history
        Route::get('/payments', [StudentFinanceController::class, 'payments'])->name('payments');
    });

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
        Route::post('/run-generate', [BillingOperationsController::class, 'runGenerate'])->name('run-generate');
        Route::post('/exceptions/{exceptionId}/fix', [BillingOperationsController::class, 'fixException'])->name('fix-exception');
        Route::post('/send-reminders', [BillingOperationsController::class, 'sendReminders'])->name('send-reminders');
    });
