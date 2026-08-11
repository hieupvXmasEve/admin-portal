<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceSetting extends Model
{
    protected $fillable = [
        'credit_offset_enabled',
        'credit_offset_min_balance',
    ];

    protected $casts = [
        'credit_offset_enabled' => 'boolean',
        'credit_offset_min_balance' => 'decimal:2',
    ];

    public static function defaults(): array
    {
        return [
            'is_singleton' => true,
            'credit_offset_enabled' => false,
            'credit_offset_min_balance' => 0,
        ];
    }

    /**
     * Singleton row — Finance settings are not campus-scoped. `is_singleton`
     * carries a unique constraint so concurrent first-ever access can never
     * create two rows (firstOrCreate re-selects on the constraint violation).
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['is_singleton' => true], self::defaults());
    }
}
