<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\BillingCycle;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;

use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $invoice_number
 * @property int $student_id
 * @property int|null $billing_cycle_id
 * @property int $semester_id
 * @property Carbon|null $due_date
 * @property string $status
 * @property-read float $total_amount
 * @property-read float $paid_amount
 * @property-read float $outstanding_balance
 */
class StudentInvoice extends Model
{
    // FIN-25: only statuses that actually exist in the student_invoices.status
    // DB enum (draft,pending,paid,partial,overdue,cancelled). The legacy values
    // 'issued'/'void' could never match a stored row, so dropping them keeps the
    // reuse scope identical while removing dead drift. Finalized invoices (paid
    // or cancelled) must not be reused for new charge generation.
    public const NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES = [
        'paid',
        'cancelled',
    ];

    protected $fillable = [
        'invoice_number',
        'student_id',
        'billing_cycle_id',
        'semester_id',
        // Cache columns (NT4/DB-14): rebuildable snapshot, written only by
        // SettlementService::recalculateInvoiceSnapshot.
        'cached_subtotal',
        'cached_discount_total',
        'cached_total_amount',
        'cached_paid_amount',
        'cached_paid_at',
        // Legacy aliases kept fillable so existing writers map transparently
        // onto the cache columns via the mutators below.
        'subtotal',
        'discount_total',
        'total_amount',
        'paid_amount',
        'paid_at',
        'status',
        'due_date',
        'last_reminder_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'cached_paid_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'cached_subtotal' => 'decimal:2',
        'cached_discount_total' => 'decimal:2',
        'cached_total_amount' => 'decimal:2',
        'cached_paid_amount' => 'decimal:2',
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
        $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($this);

        return (float) $snapshot['net'];
    }

    /**
     * Get the total paid amount of the invoice.
     */
    public function getPaidAmountAttribute(): float
    {
        $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($this);

        return (float) $snapshot['paid'];
    }

    /**
     * Calculate the outstanding balance.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($this);

        return (float) $snapshot['remaining'];
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
        $candidates = (clone $query)
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->get();

        $matchingIds = $candidates
            ->filter(function (StudentInvoice $invoice) use ($status) {
                $realTimeStatus = $invoice->real_time_status;

                return match ($status) {
                    'zero_amount' => $realTimeStatus === 'zero_amount',
                    'overdue' => $realTimeStatus === 'overdue',
                    'paid' => $realTimeStatus === 'paid',
                    'open' => $realTimeStatus === 'open',
                    default => $invoice->status === $status,
                };
            })
            ->pluck('id')
            ->all();

        if ($matchingIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $matchingIds);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get the real-time status based on balance and due date.
     */
    public function getRealTimeStatusAttribute(): string
    {
        $snapshot = app(SettlementService::class)->deriveInvoiceSnapshot($this);

        if ($snapshot['net'] <= 0) {
            return 'zero_amount';
        }

        if ($snapshot['remaining'] <= 0) {
            return 'paid';
        }

        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        return 'open';
    }

    // =====================
    // Cache column aliases (NT4/DB-14)
    // =====================
    // total_amount / paid_amount / outstanding_balance are ledger-derived above
    // (canonical truth). The aliases below keep the legacy attribute names
    // working transparently: reads of the non-derived snapshot fields return the
    // rebuildable cache, and writes of any legacy name map onto the cache column.

    public function getSubtotalAttribute(): float
    {
        return (float) ($this->cached_subtotal ?? 0);
    }

    public function getDiscountTotalAttribute(): float
    {
        return (float) ($this->cached_discount_total ?? 0);
    }

    public function getPaidAtAttribute()
    {
        return $this->cached_paid_at;
    }

    public function setSubtotalAttribute($value): void
    {
        $this->cached_subtotal = $value;
    }

    public function setDiscountTotalAttribute($value): void
    {
        $this->cached_discount_total = $value;
    }

    public function setTotalAmountAttribute($value): void
    {
        $this->cached_total_amount = $value;
    }

    public function setPaidAmountAttribute($value): void
    {
        $this->cached_paid_amount = $value;
    }

    public function setPaidAtAttribute($value): void
    {
        $this->cached_paid_at = $value;
    }
}
