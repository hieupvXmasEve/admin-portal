<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'student_id',
        'billing_cycle_id',
        'semester_id',
        'subtotal',
        'discount_total',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the student that owns the invoice.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the billing cycle for this invoice.
     */
    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    /**
     * Get the semester for this invoice.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get all items for this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    /**
     * Get all discounts for this invoice.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(InvoiceDiscount::class, 'invoice_id');
    }

    /**
     * Calculate the outstanding balance.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->paid_amount >= $this->total_amount;
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' ||
               ($this->status === 'pending' && $this->due_date->isPast() && !$this->isPaid());
    }

    /**
     * Recalculate invoice totals from items and discounts.
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('total_price');
        $this->discount_total = $this->discounts()->sum('amount');
        $this->total_amount = max(0, $this->subtotal - $this->discount_total);
        $this->save();
    }

    /**
     * Mark the invoice as paid.
     */
    public function markAsPaid(): bool
    {
        return $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_amount' => $this->total_amount,
        ]);
    }
}
