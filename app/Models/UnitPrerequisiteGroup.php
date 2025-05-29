<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitPrerequisiteGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'logic_operator',
        'description',
    ];

    protected $casts = [
        'logic_operator' => 'string',
    ];

    /**
     * Get the unit that this prerequisite group belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the conditions for this prerequisite group.
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(UnitPrerequisiteCondition::class, 'group_id');
    }
}
