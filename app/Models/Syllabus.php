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
        'curriculum_unit_id',
        'version',
        'description',
        'total_hours',
        'hours_per_session',
        'is_active',
    ];

    protected $casts = [
        'total_hours' => 'integer',
        'hours_per_session' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the curriculum unit that owns this syllabus.
     */
    public function curriculumUnit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class);
    }

    /**
     * Get the unit through curriculum unit relationship.
     */
    public function unit()
    {
        return $this->hasOneThrough(
            Unit::class,
            CurriculumUnit::class,
            'id',
            'id',
            'curriculum_unit_id',
            'unit_id'
        );
    }

    /**
     * Get the semester through curriculum unit relationship.
     */
    public function semester()
    {
        return $this->hasOneThrough(
            Semester::class,
            CurriculumUnit::class,
            'id',
            'id',
            'curriculum_unit_id',
            'semester_id'
        );
    }

    /**
     * Get the curriculum version through curriculum unit.
     */
    public function curriculumVersion()
    {
        return $this->hasOneThrough(
            CurriculumVersion::class,
            CurriculumUnit::class,
            'id',
            'id',
            'curriculum_unit_id',
            'curriculum_version_id'
        );
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

    /**
     * Check if this syllabus has a complete total hours and per session hours.
     */
    public function hasCompleteTotalHoursAndPerSessionHours(): bool
    {
        return $this->total_hours !== null && $this->hours_per_session !== null && $this->total_hours > 0 && $this->hours_per_session > 0;
    }
}
