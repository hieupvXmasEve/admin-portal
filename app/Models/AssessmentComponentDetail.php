<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponentDetail extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'assessment_component_id',
        'name',
        'canvas_assignment_id',
        'canvas_synced_at',
        'grading_type',
        'submission_types',
        'weight',
        'max_points',
        'due_date',
        'description',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'max_points' => 'decimal:2',
        'canvas_synced_at' => 'datetime',
        'due_date' => 'date',
        'submission_types' => 'array',
    ];

    /**
     * Get the assessment component that owns this detail.
     */
    public function assessmentComponent(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }

    /**
     * Get all scores for this assessment component detail.
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentComponentDetailScore::class);
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure standard logging for assessment details
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    /**
     * Get standard fields for logging
     */
    protected function getStandardLogFields(): array
    {
        return [
            'assessment_component_id',
            'name',
            'weight',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $componentName = $this->assessmentComponent?->name ?? "Component ID {$this->assessment_component_id}";
        return "{$this->name} - {$componentName}";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Added assessment detail: {$identifier}",
            'updated' => "Updated assessment detail: {$identifier}",
            'deleted' => "Removed assessment detail: {$identifier}",
            default => "{$eventName} assessment detail: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'detail_name' => $this->name,
            'detail_weight' => $this->weight,
            'parent_component' => $this->assessmentComponent?->name,
            'parent_weight' => $this->assessmentComponent?->weight,
            'unit_code' => $this->assessmentComponent?->syllabus?->curriculumUnit?->unit?->code,
        ];

        // Track weight changes
        if ($this->isDirty('weight') && $this->exists) {
            $properties['weight_change'] = [
                'from' => $this->getOriginal('weight'),
                'to' => $this->weight,
                'parent_total_weight' => $this->assessmentComponent?->getTotalDetailWeightAttribute(),
            ];
        }

        return $properties;
    }
}
