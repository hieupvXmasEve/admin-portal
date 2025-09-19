<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\StudentWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get or create a wallet for a student.
     */
    public function getOrCreateWallet(Student $student): StudentWallet
    {
        return $student->wallet()->firstOrCreate([
            'student_id' => $student->id,
        ], [
            'balance' => 0.00,
        ]);
    }

    /**
     * Get wallet balance for a student.
     */
    public function getBalance(Student $student): string
    {
        $wallet = $this->getOrCreateWallet($student);
        return number_format($wallet->balance, 2);
    }

    /**
     * Add gold to student's wallet.
     */
    public function addGold(
        Student $student,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null
    ): WalletTransaction {
        return DB::transaction(function () use ($student, $amount, $sourceType, $sourceId, $notes) {
            $wallet = $this->getOrCreateWallet($student);
            
            // Create transaction record
            $transaction = WalletTransaction::create([
                'student_id' => $student->id,
                'amount' => abs($amount), // Always positive for earning
                'type' => WalletTransaction::TYPE_EARN,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'notes' => $notes,
            ]);

            // Update wallet balance
            $wallet->increment('balance', abs($amount));
            $wallet->touch(); // Update updated_at timestamp

            return $transaction;
        });
    }

    /**
     * Deduct gold from student's wallet.
     */
    public function deductGold(
        Student $student,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null
    ): WalletTransaction {
        return DB::transaction(function () use ($student, $amount, $sourceType, $sourceId, $notes) {
            $wallet = $this->getOrCreateWallet($student);
            
            // Check if sufficient balance
            if ($wallet->balance < abs($amount)) {
                throw new \InvalidArgumentException('Insufficient wallet balance');
            }

            // Create transaction record
            $transaction = WalletTransaction::create([
                'student_id' => $student->id,
                'amount' => -abs($amount), // Always negative for spending
                'type' => WalletTransaction::TYPE_SPEND,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'notes' => $notes,
            ]);

            // Update wallet balance
            $wallet->decrement('balance', abs($amount));
            $wallet->touch(); // Update updated_at timestamp

            return $transaction;
        });
    }

    /**
     * Manually adjust student's wallet balance (staff only).
     */
    public function adjustBalance(
        Student $student,
        float $amount,
        string $notes,
        ?int $adjustedBy = null
    ): WalletTransaction {
        return DB::transaction(function () use ($student, $amount, $notes, $adjustedBy) {
            $wallet = $this->getOrCreateWallet($student);
            
            // Create transaction record
            $transaction = WalletTransaction::create([
                'student_id' => $student->id,
                'amount' => $amount, // Can be positive or negative
                'type' => WalletTransaction::TYPE_ADJUST,
                'source_type' => WalletTransaction::SOURCE_MANUAL,
                'source_id' => $adjustedBy,
                'notes' => $notes,
            ]);

            // Update wallet balance
            if ($amount > 0) {
                $wallet->increment('balance', $amount);
            } else {
                $wallet->decrement('balance', abs($amount));
            }
            $wallet->touch(); // Update updated_at timestamp

            return $transaction;
        });
    }

    /**
     * Get transaction history for a student.
     */
    public function getTransactionHistory(
        Student $student,
        int $perPage = 15,
        ?string $type = null
    ): LengthAwarePaginator {
        $query = $student->walletTransactions()
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get recent transactions for a student.
     */
    public function getRecentTransactions(Student $student, int $limit = 10): Collection
    {
        return $student->walletTransactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction statistics for a student.
     */
    public function getTransactionStats(Student $student): array
    {
        $transactions = $student->walletTransactions;

        return [
            'total_earned' => $transactions->where('type', WalletTransaction::TYPE_EARN)->sum('amount'),
            'total_spent' => abs($transactions->where('type', WalletTransaction::TYPE_SPEND)->sum('amount')),
            'total_adjustments' => $transactions->where('type', WalletTransaction::TYPE_ADJUST)->sum('amount'),
            'transaction_count' => $transactions->count(),
            'current_balance' => $this->getBalance($student),
        ];
    }

    /**
     * Check if student has sufficient balance.
     */
    public function hasSufficientBalance(Student $student, float $amount): bool
    {
        $wallet = $this->getOrCreateWallet($student);
        return $wallet->balance >= $amount;
    }

    /**
     * Get wallet with transaction summary.
     */
    public function getWalletSummary(Student $student): array
    {
        $wallet = $this->getOrCreateWallet($student);
        $stats = $this->getTransactionStats($student);
        $recentTransactions = $this->getRecentTransactions($student);

        return [
            'wallet' => $wallet,
            'stats' => $stats,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Bulk add gold to multiple students.
     */
    public function bulkAddGold(
        Collection $students,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null
    ): Collection {
        $transactions = collect();

        DB::transaction(function () use ($students, $amount, $sourceType, $sourceId, $notes, &$transactions) {
            foreach ($students as $student) {
                $transaction = $this->addGold($student, $amount, $sourceType, $sourceId, $notes);
                $transactions->push($transaction);
            }
        });

        return $transactions;
    }
}