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
| adjustments, bulk import, and transaction management.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('wallets')->name('wallets.')->group(function () {
        // List all wallets
        Route::get('/', [StudentCashWalletController::class, 'index'])
            ->middleware('can:view_student_wallet')
            ->name('index');

        // Import page
        Route::get('/import', [StudentCashWalletController::class, 'import'])
            ->middleware('can:deposit_wallet_balance')
            ->name('import');

        // Download import template
        Route::get('/template/download', [StudentCashWalletController::class, 'downloadTemplate'])
            ->middleware('can:deposit_wallet_balance')
            ->name('template.download');

        // Preview import data
        Route::post('/preview', [StudentCashWalletController::class, 'preview'])
            ->middleware('can:deposit_wallet_balance')
            ->name('preview');

        // Process import after preview
        Route::post('/process', [StudentCashWalletController::class, 'process'])
            ->middleware('can:deposit_wallet_balance')
            ->name('process');

        // View specific wallet
        Route::get('/{wallet}', [StudentCashWalletController::class, 'show'])
            ->middleware('can:view_student_wallet')
            ->name('show');

        // Deposit funds
        Route::post('/{wallet}/deposit', [StudentCashWalletController::class, 'deposit'])
            ->middleware('can:deposit_wallet_balance')
            ->name('deposit');

        // Balance adjustment (can be positive or negative)
        Route::post('/{wallet}/adjustment', [StudentCashWalletController::class, 'adjustment'])
            ->middleware('can:adjust_wallet_balance')
            ->name('adjustment');
    });
});
