<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCreditEntitlement extends Model
{
    public const STATUS_GRANTED = 'granted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    public const ALLOCATION_AVAILABLE = 'available';

    public const ALLOCATION_PARTIALLY_APPLIED = 'partially_applied';

    public const ALLOCATION_FULLY_APPLIED = 'fully_applied';

    protected $fillable = [
        'billing_account_id',
        'source_system',
        'source_kind',
        'source_ref',
        'entitlement_type',
        'lifecycle_status',
        'allocation_status',
        'amount',
        'currency',
        'pricing_rule_version',
        'pricing_snapshot',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pricing_snapshot' => 'array',
        'approved_at' => 'datetime',
    ];

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function creditApplications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }
}
