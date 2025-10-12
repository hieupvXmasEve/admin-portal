<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    protected $fillable = [
        'semester_id',
        'name',
        'start_date',
        'end_date',
        'due_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * Get the semester associated with this billing cycle.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get all invoices for this billing cycle.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }

    /**
     * Check if the billing cycle is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the billing cycle is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the billing cycle is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Activate the billing cycle.
     */
    public function activate(): bool
    {
        if ($this->isDraft()) {
            return $this->update(['status' => 'active']);
        }
        return false;
    }

    /**
     * Close the billing cycle.
     */
    public function close(): bool
    {
        if ($this->isActive()) {
            return $this->update(['status' => 'closed']);
        }
        return false;
    }
}
