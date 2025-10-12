<?php

use App\Http\Controllers\BillingCycleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Billing cycle management routes
    Route::prefix('billing-cycles')->name('billing-cycles.')->group(function () {
        Route::get('/', [BillingCycleController::class, 'index'])
            ->middleware('can:view_billing_cycle')
            ->name('index');

        Route::get('create', [BillingCycleController::class, 'create'])
            ->middleware('can:create_billing_cycle')
            ->name('create');

        Route::post('/', [BillingCycleController::class, 'store'])
            ->middleware('can:create_billing_cycle')
            ->name('store');

        Route::get('{billingCycle}', [BillingCycleController::class, 'show'])
            ->middleware('can:view_billing_cycle')
            ->name('show');

        Route::get('{billingCycle}/edit', [BillingCycleController::class, 'edit'])
            ->middleware('can:edit_billing_cycle')
            ->name('edit');

        Route::put('{billingCycle}', [BillingCycleController::class, 'update'])
            ->middleware('can:edit_billing_cycle')
            ->name('update');

        Route::delete('{billingCycle}', [BillingCycleController::class, 'destroy'])
            ->middleware('can:delete_billing_cycle')
            ->name('destroy');

        Route::post('{billingCycle}/activate', [BillingCycleController::class, 'activate'])
            ->middleware('can:activate_billing_cycle')
            ->name('activate');

        Route::post('{billingCycle}/close', [BillingCycleController::class, 'close'])
            ->middleware('can:close_billing_cycle')
            ->name('close');
    });
});
