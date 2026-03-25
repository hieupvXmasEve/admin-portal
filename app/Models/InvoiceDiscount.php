<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceDiscount extends Model
{
    protected $fillable = [
        'invoice_id',
        'discount_type',
        'discount_source',
        'description',
        'amount',
        'status',
        'approved_by',
        'memo',
        'reference_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the invoice that owns this discount.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'invoice_id');
    }

    /**
     * Get the scholarship definition if this is a scholarship discount.
     */
    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(ScholarshipDefinition::class, 'reference_id');
    }

    /**
     * Get the voucher definition if this is a voucher discount.
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(VoucherDefinition::class, 'reference_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DiscountAllocation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->amount;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($discount) {
            $discount->invoice?->recalculateTotals();
        });

        static::deleted(function ($discount) {
            $discount->invoice?->recalculateTotals();
        });
    }
}
