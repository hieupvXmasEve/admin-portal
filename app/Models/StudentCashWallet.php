<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCashWallet extends Model
{
    protected $fillable = [
        'student_id',
        'balance',
        'currency',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Get the student that owns the wallet.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    /**
     * Get recent transactions for this wallet.
     */
    public function recentTransactions(int $limit = 10): HasMany
    {
        return $this->transactions()->latest()->limit($limit);
    }

    /**
     * Check if wallet has sufficient balance for a transaction.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get formatted balance with currency.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 0, '.', ',') . ' ' . $this->currency;
    }
}
