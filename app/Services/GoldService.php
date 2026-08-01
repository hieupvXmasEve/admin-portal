<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoldTransaction;
use App\Models\Student;
use App\Models\StudentWallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gold wallet operations.
 *
 * Gold is an integer currency and debt is forbidden (balance is UNSIGNED at the
 * DB level). Every balance-changing method locks the wallet row FOR UPDATE
 * inside its transaction so concurrent requests serialize, and records a
 * balance_before/balance_after audit snapshot on the ledger entry.
 */
class GoldService
{
    /**
     * Get or create a wallet for a student.
     */
    public function getOrCreateWallet(Student $student): StudentWallet
    {
        return $student->wallet()->firstOrCreate([
            'student_id' => $student->id,
        ], [
            'balance' => 0,
        ]);
    }

    /**
     * Re-fetch the wallet under a row lock. Must be called inside a
     * transaction; every write path goes through here so balance mutations
     * cannot interleave.
     */
    private function lockWalletForUpdate(Student $student): StudentWallet
    {
        $this->getOrCreateWallet($student);

        return StudentWallet::query()
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Get wallet balance for a student.
     */
    public function getBalance(Student $student): int
    {
        return (int) $this->getOrCreateWallet($student)->balance;
    }

    /**
     * Add gold to student's wallet.
     */
    public function addGold(
        Student $student,
        int $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null,
        ?int $performedBy = null,
        string $type = GoldTransaction::TYPE_EARN
    ): GoldTransaction {
        $amount = abs($amount);

        return DB::transaction(function () use ($student, $amount, $sourceType, $sourceId, $notes, $performedBy, $type) {
            $wallet = $this->lockWalletForUpdate($student);
            $before = (int) $wallet->balance;
            $after = $before + $amount;

            $transaction = GoldTransaction::create([
                'student_id' => $student->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'type' => $type,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'performed_by' => $performedBy,
                'notes' => $notes,
            ]);

            $wallet->balance = $after;
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Deduct gold from student's wallet. Throws when the balance is
     * insufficient (never spends into debt).
     */
    public function deductGold(
        Student $student,
        int $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null,
        ?int $performedBy = null,
        string $type = GoldTransaction::TYPE_SPEND
    ): GoldTransaction {
        $amount = abs($amount);

        return DB::transaction(function () use ($student, $amount, $sourceType, $sourceId, $notes, $performedBy, $type) {
            $wallet = $this->lockWalletForUpdate($student);
            $before = (int) $wallet->balance;

            if ($before < $amount) {
                throw new InvalidArgumentException('Insufficient wallet balance');
            }

            $after = $before - $amount;

            $transaction = GoldTransaction::create([
                'student_id' => $student->id,
                'amount' => -$amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'type' => $type,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'performed_by' => $performedBy,
                'notes' => $notes,
            ]);

            $wallet->balance = $after;
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Manually adjust student's wallet balance (staff only). A negative
     * adjustment may not push the balance below zero — gold debt is forbidden.
     */
    public function adjustBalance(
        Student $student,
        int $amount,
        string $notes,
        ?int $performedBy = null
    ): GoldTransaction {
        return DB::transaction(function () use ($student, $amount, $notes, $performedBy) {
            $wallet = $this->lockWalletForUpdate($student);
            $before = (int) $wallet->balance;

            if ($amount < 0 && abs($amount) > $before) {
                throw new InvalidArgumentException('Adjustment would push balance below zero');
            }

            $after = $before + $amount;

            $transaction = GoldTransaction::create([
                'student_id' => $student->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'type' => GoldTransaction::TYPE_ADJUST,
                'source_type' => GoldTransaction::SOURCE_MANUAL,
                'source_id' => null,
                'performed_by' => $performedBy,
                'notes' => $notes,
            ]);

            $wallet->balance = $after;
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Reclaim gold for a reversed award. Locks the wallet once and, inside a
     * single transaction, deducts min(balance, amount) and writes off any
     * shortfall the student had already spent. Computing the clamp under the
     * same lock that performs the deduct means a concurrent spend cannot slip
     * between a read and the deduct and abort the reclaim.
     *
     * @return array{reclaimed: int, written_off: int, entries: list<GoldTransaction>}
     */
    public function reclaimGold(
        Student $student,
        int $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null,
        ?int $performedBy = null
    ): array {
        $amount = abs($amount);

        return DB::transaction(function () use ($student, $amount, $sourceType, $sourceId, $notes, $performedBy) {
            $wallet = $this->lockWalletForUpdate($student);
            $before = (int) $wallet->balance;
            $reclaimable = min($before, $amount);
            $entries = [];

            if ($reclaimable > 0) {
                $after = $before - $reclaimable;

                $entries[] = GoldTransaction::create([
                    'student_id' => $student->id,
                    'amount' => -$reclaimable,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'type' => GoldTransaction::TYPE_SPEND,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'performed_by' => $performedBy,
                    'notes' => $notes,
                ]);

                $wallet->balance = $after;
                $wallet->save();
                $before = $after;
            }

            $shortfall = $amount - $reclaimable;
            if ($shortfall > 0) {
                $entries[] = GoldTransaction::create([
                    'student_id' => $student->id,
                    'amount' => 0,
                    'balance_before' => $before,
                    'balance_after' => $before,
                    'type' => GoldTransaction::TYPE_WRITE_OFF,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'performed_by' => $performedBy,
                    'notes' => ($notes ?? 'Gold reclaim').' (unreclaimable: '.$shortfall.')',
                ]);
            }

            return ['reclaimed' => $reclaimable, 'written_off' => $shortfall, 'entries' => $entries];
        });
    }

    /**
     * Record a write-off: gold the system tried to reclaim but the student had
     * already spent. Audit only — it never moves the balance (amount 0), so it
     * preserves the balance == SUM(amount) invariant.
     */
    public function writeOff(
        Student $student,
        int $shortfall,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null,
        ?int $performedBy = null
    ): GoldTransaction {
        return DB::transaction(function () use ($student, $shortfall, $sourceType, $sourceId, $notes, $performedBy) {
            $wallet = $this->lockWalletForUpdate($student);
            $balance = (int) $wallet->balance;

            return GoldTransaction::create([
                'student_id' => $student->id,
                'amount' => 0,
                'balance_before' => $balance,
                'balance_after' => $balance,
                'type' => GoldTransaction::TYPE_WRITE_OFF,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'performed_by' => $performedBy,
                'notes' => ($notes ?? 'Gold write-off').' (unreclaimable: '.abs($shortfall).')',
            ]);
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
        $query = $student->goldTransactions()
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
        return $student->goldTransactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction statistics for a student.
     */
    public function getTransactionStats(Student $student): array
    {
        $transactions = $student->goldTransactions;

        return [
            'total_earned' => (int) $transactions->where('type', GoldTransaction::TYPE_EARN)->sum('amount'),
            'total_spent' => abs((int) $transactions->where('type', GoldTransaction::TYPE_SPEND)->sum('amount')),
            'total_adjustments' => (int) $transactions->where('type', GoldTransaction::TYPE_ADJUST)->sum('amount'),
            'transaction_count' => $transactions->count(),
            'current_balance' => $this->getBalance($student),
        ];
    }

    /**
     * Check if student has sufficient balance.
     */
    public function hasSufficientBalance(Student $student, int $amount): bool
    {
        return $this->getBalance($student) >= $amount;
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
     *
     * Each student is its own transaction (and its own row lock) so a large
     * batch does not hold hundreds of wallet locks until a single commit,
     * which would deadlock against concurrent checkouts.
     */
    public function bulkAddGold(
        Collection $students,
        int $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $notes = null,
        ?int $performedBy = null
    ): Collection {
        $transactions = collect();

        foreach ($students as $student) {
            $transactions->push(
                $this->addGold($student, $amount, $sourceType, $sourceId, $notes, $performedBy)
            );
        }

        return $transactions;
    }
}
