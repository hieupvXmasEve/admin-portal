<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'payment_id',
        'charge_id',
        'allocated_amount',
        'allocated_at',
        'allocated_by_user_id',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'allocated_at' => 'datetime',
    ];

    // =====================
    // Relationships
    // =====================

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class, 'charge_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }

    // =====================
    // Accessors
    // =====================

    public function getStudentAttribute(): ?Student
    {
        return $this->payment?->student;
    }
}
