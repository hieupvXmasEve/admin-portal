<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Syllabus extends AuditableModel
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

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for syllabus (course content structure)
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get comprehensive fields for logging
     */
    protected function getComprehensiveLogFields(): array
    {
        return [
            'curriculum_unit_id',
            'version',
            'description',
            'total_hours',
            'hours_per_session',
            'is_active',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $unitCode = $this->curriculumUnit?->unit?->code ?? 'N/A';
        $unitName = $this->curriculumUnit?->unit?->name;
        
        return "{$unitCode} - {$unitName} (v{$this->version})";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created syllabus: {$identifier}",
            'updated' => "Updated syllabus: {$identifier}",
            'deleted' => "Archived syllabus: {$identifier}",
            'restored' => "Restored syllabus: {$identifier}",
            default => "{$eventName} syllabus: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'unit_code' => $this->curriculumUnit?->unit?->code,
            'unit_name' => $this->curriculumUnit?->unit?->name,
            'curriculum_version' => $this->curriculumUnit?->curriculumVersion?->version_code,
            'program' => $this->curriculumUnit?->curriculumVersion?->program?->name,
            'specialization' => $this->curriculumUnit?->curriculumVersion?->specialization?->name,
            'semester' => $this->curriculumUnit?->semester?->code,
            'syllabus_version' => $this->version,
            'total_hours' => $this->total_hours,
            'hours_per_session' => $this->hours_per_session,
            'assessment_structure' => [
                'total_weight' => $this->getTotalAssessmentWeightAttribute(),
                'is_complete' => $this->hasCompleteAssessmentStructure(),
                'components_count' => $this->assessmentComponents()->count(),
            ],
            'is_active' => $this->is_active,
        ];

        // Track activation changes
        if ($this->isDirty('is_active') && $this->exists) {
            $properties['activation_change'] = [
                'from' => $this->getOriginal('is_active') ? 'active' : 'inactive',
                'to' => $this->is_active ? 'active' : 'inactive',
                'timestamp' => now()->toDateTimeString(),
            ];
        }

        // Track version changes
        if ($this->isDirty('version') && $this->exists) {
            $properties['version_change'] = [
                'from' => $this->getOriginal('version'),
                'to' => $this->version,
                'requires_review' => true,
            ];
        }

        // Track hours changes
        if (($this->isDirty('total_hours') || $this->isDirty('hours_per_session')) && $this->exists) {
            $properties['hours_change'] = [
                'total_hours' => [
                    'from' => $this->getOriginal('total_hours'),
                    'to' => $this->total_hours,
                ],
                'hours_per_session' => [
                    'from' => $this->getOriginal('hours_per_session'),
                    'to' => $this->hours_per_session,
                ],
                'sessions_count' => $this->total_hours && $this->hours_per_session ? 
                    ceil($this->total_hours / $this->hours_per_session) : null,
            ];
        }

        return $properties;
    }
}
