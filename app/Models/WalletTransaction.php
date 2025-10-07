<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentCashWallet::class, 'wallet_id');
    }

    /**
     * Get the user who created this transaction.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the polymorphic reference for this transaction.
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            return $this->morphTo('reference', 'reference_type', 'reference_id');
        }
        return null;
    }

    /**
     * Get formatted amount with sign based on transaction type.
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = in_array($this->transaction_type, ['deposit', 'refund']) ? '+' : '-';
        return $sign . number_format($this->amount, 0, '.', ',');
    }

    /**
     * Get formatted balance after transaction.
     */
    public function getFormattedBalanceAfterAttribute(): string
    {
        return number_format($this->balance_after, 0, '.', ',');
    }

    /**
     * Scope to filter by transaction type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope to get transactions for a specific reference.
     */
    public function scopeForReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }
}
