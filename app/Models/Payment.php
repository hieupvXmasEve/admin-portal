<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'amount',
        'method',
        'source',
        'external_ref',
        'paid_at',
        'status',
        'received_by_user_id',
        'raw_payload',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    // =====================
    // Constants
    // =====================

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_GATEWAY = 'gateway';
    public const METHOD_WALLET = 'wallet';
    public const METHOD_IMPORT = 'import';
    public const METHOD_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_GATEWAY,
        self::METHOD_WALLET,
        self::METHOD_IMPORT,
        self::METHOD_OTHER,
    ];

    // =====================
    // Relationships
    // =====================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    // =====================
    // Scopes
    // =====================

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // =====================
    // Accessors
    // =====================

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('allocated_amount');
    }

    public function getUnappliedAmountAttribute(): float
    {
        return (float) $this->amount - $this->allocated_amount;
    }

    public function getIsFullyAllocatedAttribute(): bool
    {
        return $this->unapplied_amount <= 0;
    }

    public function getHasUnappliedCreditAttribute(): bool
    {
        return $this->unapplied_amount > 0;
    }
}
