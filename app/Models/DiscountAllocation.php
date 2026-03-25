<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountAllocation extends Model
{
    protected $fillable = [
        'invoice_discount_id',
        'invoice_line_id',
        'amount',
        'entry_type',
        'source_ref_id',
        'source_ref_type',
        'allocation_rule',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function invoiceDiscount(): BelongsTo
    {
        return $this->belongsTo(InvoiceDiscount::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }
}
