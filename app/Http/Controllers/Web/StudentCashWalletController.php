<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Models\StudentCashWallet;
use App\Services\CashWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentCashWalletController extends Controller
{
    public function __construct(
        private CashWalletService $walletService
    ) {}

    /**
     * Process a deposit to the student's wallet.
     */
    public function deposit(DepositRequest $request, StudentCashWallet $wallet): RedirectResponse
    {
        try {
            $transaction = $this->walletService->deposit(
                $wallet->id,
                $request->validated('amount'),
                $request->validated('description')
            );

            return redirect()->back()->with('success', 'Deposit processed successfully. Transaction ID: '.$transaction->id);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process a balance adjustment (positive or negative).
     */
    public function adjustment(Request $request, StudentCashWallet $wallet): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        try {
            $transaction = $this->walletService->adjustment(
                $wallet->id,
                $request->amount,
                $request->description
            );

            $type = $request->amount > 0 ? 'credit' : 'debit';

            return redirect()->back()->with('success', "Balance {$type} adjustment processed successfully. Transaction ID: ".$transaction->id);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
