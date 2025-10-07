<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentCashWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class CashWalletService
{
    /**
     * Create a new wallet for a student.
     */
    public function createWallet(int $studentId): StudentCashWallet
    {
        // Check if student exists
        $student = Student::findOrFail($studentId);

        // Check if wallet already exists
        if ($student->cashWallet) {
            throw new Exception("Student already has a cash wallet");
        }

        return StudentCashWallet::create([
            'student_id' => $studentId,
            'balance' => 0,
            'currency' => 'VND',
        ]);
    }

    /**
     * Get wallet balance for a student.
     */
    public function getBalance(int $studentId): float
    {
        $wallet = $this->getWalletByStudentId($studentId);
        return (float) $wallet->balance;
    }

    /**
     * Get wallet by student ID, create if not exists.
     */
    public function getOrCreateWallet(int $studentId): StudentCashWallet
    {
        $student = Student::findOrFail($studentId);

        return $student->cashWallet ?? $this->createWallet($studentId);
    }

    /**
     * Get wallet by student ID.
     */
    public function getWalletByStudentId(int $studentId): StudentCashWallet
    {
        $student = Student::findOrFail($studentId);

        if (!$student->cashWallet) {
            throw new ModelNotFoundException("Student does not have a cash wallet");
        }

        return $student->cashWallet;
    }

    /**
     * Deposit money into wallet.
     */
    public function deposit(int $walletId, float $amount, string $description = null, string $referenceType = null, int $referenceId = null): WalletTransaction
    {
        return $this->createTransaction(
            $walletId,
            'deposit',
            $amount,
            $description ?? 'Cash deposit',
            $referenceType,
            $referenceId
        );
    }

    /**
     * Withdraw money from wallet.
     */
    public function withdraw(int $walletId, float $amount, string $description = null, string $referenceType = null, int $referenceId = null): WalletTransaction
    {
        return $this->createTransaction(
            $walletId,
            'payment',
            $amount,
            $description ?? 'Payment',
            $referenceType,
            $referenceId
        );
    }

    /**
     * Process refund to wallet.
     */
    public function refund(int $walletId, float $amount, string $description = null, string $referenceType = null, int $referenceId = null): WalletTransaction
    {
        return $this->createTransaction(
            $walletId,
            'refund',
            $amount,
            $description ?? 'Refund',
            $referenceType,
            $referenceId
        );
    }

    /**
     * Process balance adjustment (can be positive or negative).
     */
    public function adjustment(int $walletId, float $amount, string $description, string $referenceType = null, int $referenceId = null): WalletTransaction
    {
        return $this->createTransaction(
            $walletId,
            'adjustment',
            $amount,
            $description,
            $referenceType,
            $referenceId
        );
    }

    /**
     * Validate if wallet has sufficient balance for a transaction.
     */
    public function validateSufficientBalance(int $walletId, float $amount): bool
    {
        $wallet = StudentCashWallet::findOrFail($walletId);
        return $wallet->hasSufficientBalance($amount);
    }

    /**
     * Get transaction history for a wallet.
     */
    public function getTransactionHistory(int $walletId, int $limit = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return WalletTransaction::where('wallet_id', $walletId)
            ->with('createdBy:id,name')
            ->latest()
            ->paginate($limit);
    }

    /**
     * Get recent transactions for a wallet.
     */
    public function getRecentTransactions(int $walletId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return WalletTransaction::where('wallet_id', $walletId)
            ->with('createdBy:id,name')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Create a transaction record with balance snapshots.
     * This is a generic method for recording any type of transaction.
     */
    public function createTransaction(
        int $walletId,
        string $transactionType,
        float $amount,
        string $description = null,
        string $referenceType = null,
        int $referenceId = null
    ): WalletTransaction {
        // Validate transaction type
        if (!in_array($transactionType, ['deposit', 'payment', 'refund', 'adjustment'])) {
            throw new Exception("Invalid transaction type: {$transactionType}");
        }

        // For non-adjustment transactions, amount must be positive
        if ($transactionType !== 'adjustment' && $amount <= 0) {
            throw new Exception("Transaction amount must be positive");
        }

        // For adjustments, amount cannot be zero
        if ($transactionType === 'adjustment' && $amount == 0) {
            throw new Exception("Adjustment amount cannot be zero");
        }

        return DB::transaction(function () use ($walletId, $transactionType, $amount, $description, $referenceType, $referenceId) {
            // Lock the wallet for update to prevent race conditions
            $wallet = StudentCashWallet::lockForUpdate()->findOrFail($walletId);

            $balanceBefore = $wallet->balance;

            // Calculate balance after based on transaction type
            $balanceAfter = match ($transactionType) {
                'deposit', 'refund' => $balanceBefore + $amount,
                'payment' => $balanceBefore - $amount,
                'adjustment' => $balanceBefore + $amount, // Amount can be negative for adjustments
            };

            // Validate balance won't go negative
            if ($balanceAfter < 0) {
                $transactionName = match ($transactionType) {
                    'payment' => 'payment',
                    'adjustment' => 'adjustment',
                    default => 'transaction'
                };
                throw new Exception("Insufficient balance for {$transactionName}. Current balance: " . number_format($balanceBefore, 0, '.', ',') . " VND");
            }

            // Update wallet balance
            $wallet->update(['balance' => $balanceAfter]);

            // For transaction record, store absolute amount for adjustments, actual amount for others
            $recordAmount = $transactionType === 'adjustment' ? abs($amount) : $amount;

            // Create transaction record with balance snapshots
            return WalletTransaction::create([
                'wallet_id' => $walletId,
                'transaction_type' => $transactionType,
                'amount' => $recordAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description ?? ucfirst($transactionType),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Process payment with validation and rollback handling.
     * This method handles payment processing with proper validation and error handling.
     */
    public function processPayment(
        int $walletId,
        float $amount,
        string $description,
        string $referenceType = null,
        int $referenceId = null,
        callable $onSuccess = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new Exception("Payment amount must be positive");
        }

        return DB::transaction(function () use ($walletId, $amount, $description, $referenceType, $referenceId, $onSuccess) {
            try {
                // Validate sufficient balance before processing
                if (!$this->validateSufficientBalance($walletId, $amount)) {
                    $currentBalance = $this->getBalance($this->getWalletByStudentId($this->getStudentIdFromWallet($walletId)));
                    throw new Exception("Insufficient balance. Current balance: " . number_format($currentBalance, 0, '.', ',') . " VND, Required: " . number_format($amount, 0, '.', ',') . " VND");
                }

                // Create the payment transaction
                $transaction = $this->createTransaction(
                    $walletId,
                    'payment',
                    $amount,
                    $description,
                    $referenceType,
                    $referenceId
                );

                // Execute success callback if provided (e.g., update invoice status)
                if ($onSuccess && is_callable($onSuccess)) {
                    $callbackResult = $onSuccess($transaction);

                    // If callback returns false, rollback the transaction
                    if ($callbackResult === false) {
                        throw new Exception("Payment processing failed during callback execution");
                    }
                }

                return $transaction;

            } catch (Exception $e) {
                // The DB::transaction will automatically rollback on exception
                // Log the error for debugging
                \Log::error('Payment processing failed', [
                    'wallet_id' => $walletId,
                    'amount' => $amount,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'error' => $e->getMessage()
                ]);

                // Re-throw the exception to be handled by the caller
                throw $e;
            }
        });
    }

    /**
     * Process invoice payment from wallet.
     * This method will be fully implemented when invoice models are available.
     */
    public function processInvoicePayment(int $invoiceId, int $walletId): WalletTransaction
    {
        // For now, we'll implement a basic structure that can be extended
        // when invoice models are available

        // This would typically:
        // 1. Load the invoice and validate it exists and is payable
        // 2. Get the amount due
        // 3. Process the payment
        // 4. Update invoice status to paid

        return $this->processPayment(
            $walletId,
            0, // This would be the invoice amount
            "Invoice payment for invoice #{$invoiceId}",
            'invoice',
            $invoiceId,
            function ($transaction) use ($invoiceId) {
                // This callback would update the invoice status when invoice models exist
                // For now, just return true to indicate success
                return true;
            }
        );
    }

    /**
     * Get student ID from wallet ID.
     */
    private function getStudentIdFromWallet(int $walletId): int
    {
        $wallet = StudentCashWallet::findOrFail($walletId);
        return $wallet->student_id;
    }

    /**
     * Validate payment before processing.
     */
    public function validatePayment(int $walletId, float $amount, array $additionalChecks = []): array
    {
        $errors = [];

        // Basic amount validation
        if ($amount <= 0) {
            $errors[] = "Payment amount must be positive";
        }

        // Check if wallet exists
        try {
            $wallet = StudentCashWallet::findOrFail($walletId);
        } catch (ModelNotFoundException $e) {
            $errors[] = "Wallet not found";
            return ['valid' => false, 'errors' => $errors];
        }

        // Check sufficient balance
        if (!$this->validateSufficientBalance($walletId, $amount)) {
            $errors[] = "Insufficient balance. Current balance: " . number_format($wallet->balance, 0, '.', ',') . " VND, Required: " . number_format($amount, 0, '.', ',') . " VND";
        }

        // Execute additional custom checks if provided
        foreach ($additionalChecks as $check) {
            if (is_callable($check)) {
                $checkResult = $check($wallet, $amount);
                if (is_string($checkResult)) {
                    $errors[] = $checkResult;
                } elseif (is_array($checkResult)) {
                    $errors = array_merge($errors, $checkResult);
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'wallet' => $wallet ?? null
        ];
    }

    /**
     * Process payment with comprehensive validation and error handling.
     */
    public function processPaymentWithValidation(
        int $walletId,
        float $amount,
        string $description,
        string $referenceType = null,
        int $referenceId = null,
        array $validationChecks = [],
        callable $onSuccess = null,
        callable $onFailure = null
    ): array {
        // Validate payment first
        $validation = $this->validatePayment($walletId, $amount, $validationChecks);

        if (!$validation['valid']) {
            if ($onFailure && is_callable($onFailure)) {
                $onFailure($validation['errors']);
            }

            return [
                'success' => false,
                'errors' => $validation['errors'],
                'transaction' => null
            ];
        }

        try {
            $transaction = $this->processPayment(
                $walletId,
                $amount,
                $description,
                $referenceType,
                $referenceId,
                $onSuccess
            );

            return [
                'success' => true,
                'errors' => [],
                'transaction' => $transaction
            ];

        } catch (Exception $e) {
            if ($onFailure && is_callable($onFailure)) {
                $onFailure([$e->getMessage()]);
            }

            return [
                'success' => false,
                'errors' => [$e->getMessage()],
                'transaction' => null
            ];
        }
    }

    /**
     * Reverse a payment transaction (create a refund).
     */
    public function reversePayment(int $originalTransactionId, string $reason): WalletTransaction
    {
        $originalTransaction = WalletTransaction::findOrFail($originalTransactionId);

        if ($originalTransaction->transaction_type !== 'payment') {
            throw new Exception("Can only reverse payment transactions");
        }

        return $this->refund(
            $originalTransaction->wallet_id,
            $originalTransaction->amount,
            "Reversal: {$reason} (Original: {$originalTransaction->description})",
            $originalTransaction->reference_type,
            $originalTransaction->reference_id
        );
    }

    /**
     * Get wallet statistics.
     */
    public function getWalletStats(int $walletId): array
    {
        $wallet = StudentCashWallet::findOrFail($walletId);

        $transactions = WalletTransaction::where('wallet_id', $walletId);

        return [
            'current_balance' => $wallet->balance,
            'total_deposits' => $transactions->clone()->where('transaction_type', 'deposit')->sum('amount'),
            'total_payments' => $transactions->clone()->where('transaction_type', 'payment')->sum('amount'),
            'total_refunds' => $transactions->clone()->where('transaction_type', 'refund')->sum('amount'),
            'transaction_count' => $transactions->count(),
            'last_transaction_date' => $transactions->latest()->first()?->created_at,
        ];
    }
}
