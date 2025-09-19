<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class WalletTransaction extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'wallet_transactions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'student_id',
        'amount',
        'type',
        'source_type',
        'source_id',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     * Only tracks created_at, not updated_at.
     */
    public $timestamps = false;

    /**
     * Transaction types.
     */
    public const TYPE_EARN = 'earn';
    public const TYPE_SPEND = 'spend';
    public const TYPE_ADJUST = 'adjust';

    /**
     * Source types.
     */
    public const SOURCE_EVENT = 'event';
    public const SOURCE_REWARD = 'reward';
    public const SOURCE_MANUAL = 'manual';

    /**
     * Get the student that owns this transaction.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the wallet for this transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentWallet::class, 'student_id', 'student_id');
    }

    /**
     * Scope a query to only include transactions of a given type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include transactions from a given source type.
     */
    public function scopeFromSource(Builder $query, string $sourceType): Builder
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Scope a query to only include earning transactions.
     */
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EARN);
    }

    /**
     * Scope a query to only include spending transactions.
     */
    public function scopeSpending(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SPEND);
    }

    /**
     * Scope a query to only include adjustment transactions.
     */
    public function scopeAdjustments(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ADJUST);
    }

    /**
     * Check if this transaction is positive (increases balance).
     */
    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this transaction is negative (decreases balance).
     */
    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get the absolute amount of the transaction.
     */
    public function getAbsoluteAmountAttribute(): string
    {
        return number_format(abs($this->amount), 2);
    }
}
