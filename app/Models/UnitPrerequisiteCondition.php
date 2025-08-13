<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPrerequisiteCondition extends AuditableModel
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

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure standard logging for prerequisite conditions
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
            'group_id',
            'type',
            'required_unit_id',
            'required_credits',
            'free_text',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        if ($this->required_unit_id) {
            $unitCode = $this->requiredUnit?->code ?? "Unit ID {$this->required_unit_id}";
            return "{$this->type}: {$unitCode}";
        } elseif ($this->required_credits) {
            return "{$this->type}: {$this->required_credits} credits";
        } else {
            return "{$this->type}: {$this->free_text}";
        }
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Added prerequisite condition: {$identifier}",
            'updated' => "Updated prerequisite condition: {$identifier}",
            'deleted' => "Removed prerequisite condition: {$identifier}",
            default => "{$eventName} prerequisite condition: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'condition_type' => $this->type,
            'parent_group_logic' => $this->group?->logic_operator,
            'parent_unit_code' => $this->group?->unit?->code,
        ];

        if ($this->required_unit_id) {
            $properties['required_unit'] = [
                'code' => $this->requiredUnit?->code,
                'name' => $this->requiredUnit?->name,
                'credit_points' => $this->requiredUnit?->credit_points,
            ];
        }

        if ($this->required_credits) {
            $properties['required_credits'] = $this->required_credits;
        }

        if ($this->free_text) {
            $properties['free_text_condition'] = $this->free_text;
        }

        // Track type changes
        if ($this->isDirty('type') && $this->exists) {
            $properties['type_change'] = [
                'from' => $this->getOriginal('type'),
                'to' => $this->type,
                'impact' => 'affects_prerequisite_logic',
            ];
        }

        return $properties;
    }
}
