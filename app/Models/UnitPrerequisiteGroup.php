<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitPrerequisiteGroup extends AuditableModel
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

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for prerequisite groups (critical academic rules)
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
            'unit_id',
            'logic_operator',
            'description',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $unitCode = $this->unit?->code ?? "Unit ID {$this->unit_id}";
        $unitName = $this->unit?->name;
        
        return "Prerequisites for {$unitCode} - {$unitName} ({$this->logic_operator})";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created prerequisite group: {$identifier}",
            'updated' => "Updated prerequisite group: {$identifier}",
            'deleted' => "Removed prerequisite group: {$identifier}",
            'restored' => "Restored prerequisite group: {$identifier}",
            default => "{$eventName} prerequisite group: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'unit_code' => $this->unit?->code,
            'unit_name' => $this->unit?->name,
            'logic_operator' => $this->logic_operator,
            'description' => $this->description,
            'conditions_count' => $this->conditions()->count(),
            'prerequisite_units' => $this->getPrerequisiteUnits(),
        ];

        // Track logic operator changes
        if ($this->isDirty('logic_operator') && $this->exists) {
            $properties['logic_change'] = [
                'from' => $this->getOriginal('logic_operator'),
                'to' => $this->logic_operator,
                'impact' => 'affects_enrollment_eligibility',
            ];
        }

        return $properties;
    }

    /**
     * Get list of prerequisite units
     */
    private function getPrerequisiteUnits(): array
    {
        return $this->conditions()
            ->with('requiredUnit')
            ->get()
            ->map(function ($condition) {
                return [
                    'code' => $condition->requiredUnit?->code,
                    'name' => $condition->requiredUnit?->name,
                    'type' => $condition->type,
                ];
            })
            ->filter()
            ->toArray();
    }
}
