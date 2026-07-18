<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAccount extends Model
{
    protected $fillable = [
        'student_id',
    ];

    public function financeObligations(): HasMany
    {
        return $this->hasMany(FinanceObligation::class);
    }

    public function financeCreditEntitlements(): HasMany
    {
        return $this->hasMany(FinanceCreditEntitlement::class);
    }

    public function financeDiscountEntitlements(): HasMany
    {
        return $this->hasMany(FinanceDiscountEntitlement::class);
    }
}
