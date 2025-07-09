<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'name',
        'weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    /**
     * Get the assessment component that owns this detail.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'component_id');
    }

    /**
     * Get all scores for this assessment component detail.
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentComponentDetailScore::class);
    }
}
