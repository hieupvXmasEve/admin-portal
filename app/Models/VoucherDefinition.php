<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class VoucherDefinition extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'voucher_type',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'discount_value' => 'decimal:0',
    ];

    /**
     * Get all redemptions for this voucher
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class, 'voucher_id');
    }

    /**
     * Check if voucher is valid for a given date
     */
    public function isValidOn(Carbon $date): bool
    {
        return $this->is_active
            && $date->greaterThanOrEqualTo($this->valid_from)
            && $date->lessThanOrEqualTo($this->valid_until);
    }

    /**
     * Check if voucher is currently valid
     */
    public function isCurrentlyValid(): bool
    {
        return $this->isValidOn(now());
    }

    /**
     * Get redemption count
     */
    public function getRedemptionCountAttribute(): int
    {
        return $this->redemptions()->count();
    }
}
