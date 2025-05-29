<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPrerequisiteCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'type',
        'required_unit_id',
        'required_credits',
        'free_text',
    ];

    protected $casts = [
        'type' => 'string',
        'required_credits' => 'integer',
    ];

    /**
     * Get the prerequisite group that this condition belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UnitPrerequisiteGroup::class, 'group_id');
    }

    /**
     * Get the required unit for this prerequisite condition.
     */
    public function requiredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'required_unit_id');
    }
}
