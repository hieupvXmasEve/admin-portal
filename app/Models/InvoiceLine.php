<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'charge_id',
        'amount_snapshot',
        'description_snapshot',
    ];

    protected $casts = [
        'amount_snapshot' => 'decimal:2',
    ];

    // =====================
    // Relationships
    // =====================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'invoice_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class, 'charge_id');
    }

    // =====================
    // Accessors
    // =====================

    public function getStudentAttribute(): ?Student
    {
        return $this->invoice?->student;
    }

    public function getIsChargeAttribute(): bool
    {
        return $this->amount_snapshot > 0;
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->amount_snapshot < 0;
    }
}
