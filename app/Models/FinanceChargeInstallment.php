<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Database\Factories\FinanceChargeInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceChargeInstallment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_AWAITING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'finance_charge_id',
        'installment_no',
        'amount',
        'due_date',
        'status',
        'dng_payment_request_id',
        'paid_at',
        'push_attempt_count',
        'last_push_error',
        'last_push_attempted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'last_push_attempted_at' => 'datetime',
        'push_attempt_count' => 'integer',
        'installment_no' => 'integer',
    ];

    // =====================
    // Relationships
    // =====================

    public function charge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class, 'finance_charge_id');
    }

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    // =====================
    // Scopes
    // =====================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAwaitingPayment($query)
    {
        return $query->where('status', self::STATUS_AWAITING_PAYMENT);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeForCharge($query, int $chargeId)
    {
        return $query->where('finance_charge_id', $chargeId);
    }

    // =====================
    // Accessors
    // =====================

    public function getHasPushErrorAttribute(): bool
    {
        return $this->last_push_error !== null
            && $this->status === self::STATUS_PENDING;
    }

    // =====================
    // Factory
    // =====================

    protected static function newFactory(): FinanceChargeInstallmentFactory
    {
        return FinanceChargeInstallmentFactory::new();
    }
}
