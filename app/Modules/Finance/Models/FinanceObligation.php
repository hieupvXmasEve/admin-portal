<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinanceObligation extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'billing_account_id',
        'source_system',
        'source_kind',
        'source_ref',
        'obligation_type',
        'lifecycle_status',
        'amount',
        'currency',
        'pricing_rule_version',
        'pricing_snapshot',
        'accepted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pricing_snapshot' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function financeCharge(): HasOne
    {
        return $this->hasOne(FinanceCharge::class, 'finance_obligation_id');
    }
}
