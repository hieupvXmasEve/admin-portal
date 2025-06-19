<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquivalentUnit extends Model
{
    /** @use HasFactory<\Database\Factories\EquivalentUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'equivalent_unit_id',
        'reason',
    ];

    /**
     * Get the unit that has the equivalence.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the equivalent unit.
     */
    public function equivalentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'equivalent_unit_id');
    }
}
