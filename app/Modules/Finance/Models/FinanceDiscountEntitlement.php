<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceDiscountEntitlement extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const ALLOCATION_AVAILABLE = 'available';

    public const ALLOCATION_PARTIALLY_ALLOCATED = 'partially_allocated';

    public const ALLOCATION_FULLY_ALLOCATED = 'fully_allocated';

    public const ALLOCATION_RELEASED = 'released';

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

    public function invoiceDiscounts(): HasMany
    {
        return $this->hasMany(InvoiceDiscount::class);
    }
}
