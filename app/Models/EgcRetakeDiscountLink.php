<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgcRetakeDiscountLink extends Model
{
    protected $fillable = [
        'invoice_discount_id',
        'source_egc_block_id',
        'target_finance_charge_id',
    ];

    public function invoiceDiscount(): BelongsTo
    {
        return $this->belongsTo(InvoiceDiscount::class, 'invoice_discount_id');
    }

    public function sourceEgcBlock(): BelongsTo
    {
        return $this->belongsTo(EgcBlock::class, 'source_egc_block_id');
    }

    public function targetFinanceCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class, 'target_finance_charge_id');
    }
}
