<?php

use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Voucher management routes
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])
            ->middleware('can:view_voucher')
            ->name('index');

        Route::get('create', [VoucherController::class, 'create'])
            ->middleware('can:create_voucher')
            ->name('create');

        Route::post('/', [VoucherController::class, 'store'])
            ->middleware('can:create_voucher')
            ->name('store');

        Route::get('{voucher}/edit', [VoucherController::class, 'edit'])
            ->middleware('can:edit_voucher')
            ->name('edit');

        Route::put('{voucher}', [VoucherController::class, 'update'])
            ->middleware('can:edit_voucher')
            ->name('update');

        // Voucher redemption
        Route::post('redeem', [VoucherController::class, 'redeem'])
            ->middleware('can:edit_voucher')
            ->name('redeem');

        Route::get('{voucher}', [VoucherController::class, 'show'])
            ->middleware('can:view_voucher')
            ->name('show');
    });
});
