<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class FinancePricingCatalogItem extends Model
{
    protected $fillable = [
        'obligation_type',
        'amount',
        'currency',
        'rule_version',
        'description',
        'facts_match',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'facts_match' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];
}
