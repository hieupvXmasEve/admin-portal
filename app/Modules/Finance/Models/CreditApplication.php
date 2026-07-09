<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditApplication extends Model
{
    public const ENTRY_APPLICATION = 'application';

    public const ENTRY_REVERSAL = 'reversal';

    protected $fillable = [
        'finance_credit_entitlement_id',
        'invoice_line_id',
        'amount',
        'entry_type',
        'source_ref_id',
        'source_ref_type',
        'applied_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function financeCreditEntitlement(): BelongsTo
    {
        return $this->belongsTo(FinanceCreditEntitlement::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
