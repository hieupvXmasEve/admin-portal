<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'paid_amount',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the invoice that owns this item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'invoice_id');
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Calculate and set the total price based on quantity and unit price.
     */
    public function calculateTotal(): void
    {
        $this->total_price = $this->quantity * $this->unit_price;
    }

    /**
     * Get the remaining amount to be paid for this item.
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->total_price - $this->paid_amount);
    }

    /**
     * Check if the item is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total_price;
    }

    /**
     * Check if the item is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->paid_amount < $this->total_price;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotal();
        });

        static::saved(function ($item) {
            $item->invoice->recalculateTotals();
        });

        static::deleted(function ($item) {
            $item->invoice->recalculateTotals();
        });
    }
}
