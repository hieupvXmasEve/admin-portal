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
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
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
