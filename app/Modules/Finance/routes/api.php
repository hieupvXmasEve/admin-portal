<?php

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
