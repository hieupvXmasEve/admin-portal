<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $invoice_number
 * @property int $student_id
 * @property int|null $billing_cycle_id
 * @property int $semester_id
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $status
 * @property-read float $total_amount
 * @property-read float $paid_amount
 * @property-read float $outstanding_balance
 */
class StudentInvoice extends Model
{
    public const NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES = [
        'issued',
        'paid',
        'void',
        'cancelled',
    ];

    protected $fillable = [
        'invoice_number',
        'student_id',
        'billing_cycle_id',
        'semester_id',
        'subtotal',
        'discount_total',
        'total_amount',
        'paid_amount',
        'paid_at',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the student that owns the invoice.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the semester for this invoice.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the billing cycle for this invoice.
     */
    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    /**
     * Get all invoice lines (charge-based) for this invoice.
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(InvoiceDiscount::class, 'invoice_id');
    }

    /**
     * Get all charges linked to this invoice through invoice lines.
     */
    public function charges()
    {
        return $this->hasManyThrough(
            FinanceCharge::class,
            InvoiceLine::class,
            'invoice_id', // Foreign key on invoice_lines table...
            'id',         // Foreign key on finance_charges table...
            'id',         // Local key on student_invoices table...
            'charge_id'   // Local key on invoice_lines table...
        );
    }

    /**
     * Get the total amount of the invoice.
     */
    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->attributes['total_amount'] ?? 0);
    }

    /**
     * Get the total paid amount of the invoice.
     */
    public function getPaidAmountAttribute(): float
    {
        return (float) ($this->attributes['paid_amount'] ?? 0);
    }

    /**
     * Calculate the outstanding balance.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    /**
     * Get the outstanding amount for this invoice.
     */
    public function getOutstandingAmount(): float
    {
        return $this->outstanding_balance;
    }

    /**
     * Mark the invoice as paid.
     * Note: In the new system, status should probably be driven by allocations.
     */
    public function markAsPaid(): bool
    {
        return $this->update([
            'status' => 'paid',
        ]);
    }

    public function recalculateTotals(): void
    {
        app(SettlementService::class)->recalculateInvoiceSnapshot($this);
    }

    // =====================
    // Scopes
    // =====================

    public function scopeForCampus($query, int $campusId)
    {
        return $query->whereHas('student', function ($q) use ($campusId) {
            $q->where('campus_id', $campusId);
        });
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeReusableForChargeGeneration($query)
    {
        return $query->whereNotIn('status', self::NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('invoice_number', 'like', "%{$term}%")
                ->orWhereHas('student', function ($subQ) use ($term) {
                    $subQ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('student_id', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Scope to filter by real-time status.
     * Since status is calculated, we might need to filter manually or use having clauses if we aggregate.
     * For simplicity and performance in standard SQL without complex subqueries,
     * we will implement a basic version here. For stricter filtering, we might need DB raw queries.
     */
    public function scopeFilterByStatus($query, string $status)
    {
        switch ($status) {
            case 'zero_amount':
                return $query->where('total_amount', '<=', 0);

            case 'overdue':
                return $query->where('due_date', '<', now())
                    ->whereColumn('paid_amount', '<', 'total_amount');

            case 'paid':
                return $query->whereColumn('paid_amount', '>=', 'total_amount');

            case 'open':
                return $query->where('due_date', '>=', now())
                    ->whereColumn('paid_amount', '<', 'total_amount')
                    ->where('total_amount', '>', 0);

            default:
                return $query->where('status', $status);
        }
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get the real-time status based on balance and due date.
     */
    public function getRealTimeStatusAttribute(): string
    {
        if ($this->total_amount <= 0) {
            return 'zero_amount';
        }

        if ($this->outstanding_balance <= 0) {
            return 'paid';
        }

        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        return 'open';
    }
}
