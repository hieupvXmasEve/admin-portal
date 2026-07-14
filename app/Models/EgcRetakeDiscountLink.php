<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgcRetakeDiscountLink extends Model
{
    protected $fillable = [
        'invoice_discount_id',
        'source_egc_block_id',
        'target_invoice_line_id',
    ];

    public function invoiceDiscount(): BelongsTo
    {
        return $this->belongsTo(InvoiceDiscount::class, 'invoice_discount_id');
    }

    public function sourceEgcBlock(): BelongsTo
    {
        return $this->belongsTo(EgcBlock::class, 'source_egc_block_id');
    }

    public function targetInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'target_invoice_line_id');
    }
}
