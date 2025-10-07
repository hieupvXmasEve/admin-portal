<?php

declare(strict_types=1);

use App\Http\Controllers\Web\StudentCashWalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Cash Wallet Routes
|--------------------------------------------------------------------------
|
| Routes for managing student cash wallet operations including deposits,
| adjustments, and transaction management.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('wallets/{wallet}')->name('wallets.')->group(function () {
        // Deposit funds
        Route::post('/deposit', [StudentCashWalletController::class, 'deposit'])
            ->middleware('can:wallets.deposit')
            ->name('deposit');

        // Balance adjustment (can be positive or negative)
        Route::post('/adjustment', [StudentCashWalletController::class, 'adjustment'])
            ->middleware('can:wallets.adjust')
            ->name('adjustment');
    });
});
