<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Syllabus extends Model
{
    use HasFactory;

    protected $table = 'syllabus';

    protected $fillable = [
        'unit_id',
        'version',
        'description',
        'total_hours',
        'hours_per_session',
        'effective_from_semester_id',
        'is_active',
    ];

    protected $casts = [
        'total_hours' => 'integer',
        'hours_per_session' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the unit that owns this syllabus.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester from which this syllabus is effective.
     */
    public function effectiveFromSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'effective_from_semester_id');
    }

    /**
     * Get all assessment components for this syllabus.
     */
    public function assessmentComponents(): HasMany
    {
        return $this->hasMany(AssessmentComponent::class);
    }

    /**
     * Calculate the total weight of all assessment components.
     */
    public function getTotalAssessmentWeightAttribute(): float
    {
        return $this->assessmentComponents->sum('weight') ?? 0.0;
    }

    /**
     * Check if this syllabus has a complete assessment structure (100% weight).
     */
    public function hasCompleteAssessmentStructure(): bool
    {
        return $this->getTotalAssessmentWeightAttribute() === 100.0;
    }
}
