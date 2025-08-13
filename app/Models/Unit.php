<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\UnitFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'credit_points',
    ];

    protected $casts = [
        'credit_points' => 'decimal:2',
    ];

    /**
     * Get the curriculum units for this unit.
     */
    public function curriculumUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class);
    }

    /**
     * Get the equivalent units for this unit.
     */
    public function equivalentUnits(): HasMany
    {
        return $this->hasMany(EquivalentUnit::class);
    }

    /**
     * Get the units that are equivalent to this unit.
     */
    public function equivalentTo(): HasMany
    {
        return $this->hasMany(EquivalentUnit::class, 'equivalent_unit_id');
    }

    /**
     * Get the prerequisite groups for this unit.
     */
    public function prerequisiteGroups(): HasMany
    {
        return $this->hasMany(UnitPrerequisiteGroup::class);
    }

    /**
     * Get the prerequisite conditions for this unit through groups.
     */
    public function prerequisiteConditions()
    {
        return $this->hasManyThrough(
            UnitPrerequisiteCondition::class,
            UnitPrerequisiteGroup::class,
            'unit_id',
            'group_id'
        );
    }

    /**
     * Get all syllabus for this unit through curriculum units.
     */
    public function syllabus()
    {
        return $this->hasManyThrough(
            Syllabus::class,
            CurriculumUnit::class,
            'unit_id',
            'curriculum_unit_id'
        );
    }

    /**
     * Get the active syllabus for this unit through curriculum units.
     */
    public function activeSyllabus()
    {
        return $this->hasManyThrough(
            Syllabus::class,
            CurriculumUnit::class,
            'unit_id',
            'curriculum_unit_id'
        )->where('syllabus.is_active', true);
    }

    /**
     * Check if this unit can be used as an elective for a given specialization.
     */
    public function canBeElectiveFor(int $specializationId, int $programId): bool
    {
        // Logic để kiểm tra unit có thể được chọn làm môn tự chọn không
        // Unit không thể là elective cho chính specialization của nó
        $isFromSameSpecialization = $this->curriculumUnits()
            ->whereHas('curriculumVersion', function ($query) use ($specializationId) {
                $query->where('specialization_id', $specializationId);
            })
            ->exists();

        return ! $isFromSameSpecialization;
    }

    /**
     * Get available elective units for a specific specialization and curriculum version.
     */
    public static function getAvailableElectives(int $specializationId, int $programId): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->whereDoesntHave('curriculumUnits', function ($query) use ($specializationId) {
                $query->whereHas('curriculumVersion', function ($q) use ($specializationId) {
                    $q->where('specialization_id', $specializationId);
                });
            })
            ->where(function ($query) use ($programId, $specializationId) {
                // Units from other specializations in same program
                $query->whereHas('curriculumUnits.curriculumVersion', function ($q) use ($programId, $specializationId) {
                    $q->where('program_id', $programId)
                        ->where('specialization_id', '!=', $specializationId);
                })
                    // Or units from other programs
                    ->orWhereHas('curriculumUnits.curriculumVersion', function ($q) use ($programId) {
                        $q->where('program_id', '!=', $programId);
                    })
                    // Or unassigned units
                    ->orWhereDoesntHave('curriculumUnits');
            });
    }

    /**
     * Get units from other specializations within the same program.
     */
    public static function getFromOtherSpecializationsInProgram(int $programId, int $excludeSpecializationId): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->whereHas('curriculumUnits.curriculumVersion', function ($query) use ($programId, $excludeSpecializationId) {
                $query->where('program_id', $programId)
                    ->where('specialization_id', '!=', $excludeSpecializationId);
            })
            ->distinct();
    }

    /**
     * Get units from other programs.
     */
    public static function getFromOtherPrograms(int $excludeProgramId): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->whereHas('curriculumUnits.curriculumVersion', function ($query) use ($excludeProgramId) {
                $query->where('program_id', '!=', $excludeProgramId);
            })
            ->distinct();
    }

    /**
     * Get unassigned units (not in any curriculum).
     */
    public static function getUnassignedUnits(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->whereDoesntHave('curriculumUnits');
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for units (core academic data)
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
            'code',
            'name',
            'credit_points',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Custom activity descriptions for unit events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created unit: {$identifier}",
            'updated' => "Updated unit: {$identifier}",
            'deleted' => "Archived unit: {$identifier}",
            'restored' => "Restored unit: {$identifier}",
            default => "{$eventName} unit: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'unit_code' => $this->code,
            'unit_name' => $this->name,
            'credit_points' => $this->credit_points,
            'curriculum_usage' => $this->getCurriculumUsageStats(),
            'prerequisite_info' => $this->getPrerequisiteInfo(),
            'equivalency_info' => $this->getEquivalencyInfo(),
            'active_syllabus_count' => $this->activeSyllabus()->count(),
        ];

        // Track credit point changes (critical for graduation requirements)
        if ($this->isDirty('credit_points') && $this->exists) {
            $properties['credit_change'] = [
                'from' => $this->getOriginal('credit_points'),
                'to' => $this->credit_points,
                'impact' => 'affects_graduation_requirements',
                'affected_curriculum_versions' => $this->getAffectedCurriculumVersions(),
            ];
        }

        // Track code changes (rare but critical)
        if ($this->isDirty('code') && $this->exists) {
            $properties['code_change'] = [
                'from' => $this->getOriginal('code'),
                'to' => $this->code,
                'requires_update' => [
                    'transcripts',
                    'course_registrations',
                    'academic_records',
                ],
            ];
        }

        // Track name changes
        if ($this->isDirty('name') && $this->exists) {
            $properties['name_change'] = [
                'from' => $this->getOriginal('name'),
                'to' => $this->name,
            ];
        }

        return $properties;
    }

    /**
     * Get curriculum usage statistics
     */
    private function getCurriculumUsageStats(): array
    {
        $curriculumUnits = $this->curriculumUnits()->with('curriculumVersion')->get();
        
        return [
            'total_curriculum_versions' => $curriculumUnits->pluck('curriculum_version_id')->unique()->count(),
            'as_core_unit' => $curriculumUnits->where('type', 'core')->count(),
            'as_elective' => $curriculumUnits->where('type', 'elective')->count(),
            'as_specialization' => $curriculumUnits->where('type', 'specialization')->count(),
            'programs_using' => $curriculumUnits->map(function ($cu) {
                return $cu->curriculumVersion?->program?->code;
            })->filter()->unique()->values()->toArray(),
            'specializations_using' => $curriculumUnits->map(function ($cu) {
                return $cu->curriculumVersion?->specialization?->code;
            })->filter()->unique()->values()->toArray(),
        ];
    }

    /**
     * Get prerequisite information
     */
    private function getPrerequisiteInfo(): array
    {
        $prerequisiteGroups = $this->prerequisiteGroups()->with('conditions.prerequisiteUnit')->get();
        
        return [
            'has_prerequisites' => $prerequisiteGroups->isNotEmpty(),
            'prerequisite_groups_count' => $prerequisiteGroups->count(),
            'total_conditions' => $prerequisiteGroups->sum(function ($group) {
                return $group->conditions->count();
            }),
            'prerequisite_units' => $prerequisiteGroups->flatMap(function ($group) {
                return $group->conditions->map(function ($condition) {
                    return $condition->prerequisiteUnit?->code;
                });
            })->filter()->unique()->values()->toArray(),
        ];
    }

    /**
     * Get equivalency information
     */
    private function getEquivalencyInfo(): array
    {
        $equivalentUnits = $this->equivalentUnits()->with('equivalentUnit')->get();
        $equivalentTo = $this->equivalentTo()->with('unit')->get();
        
        return [
            'has_equivalents' => $equivalentUnits->isNotEmpty() || $equivalentTo->isNotEmpty(),
            'equivalent_units' => $equivalentUnits->map(function ($eu) {
                return $eu->equivalentUnit?->code;
            })->filter()->toArray(),
            'equivalent_to_units' => $equivalentTo->map(function ($eu) {
                return $eu->unit?->code;
            })->filter()->toArray(),
        ];
    }

    /**
     * Get affected curriculum versions when unit changes
     */
    private function getAffectedCurriculumVersions(): array
    {
        return $this->curriculumUnits()
            ->with('curriculumVersion')
            ->get()
            ->map(function ($cu) {
                $cv = $cu->curriculumVersion;
                return $cv ? "{$cv->program?->code}/{$cv->specialization?->code}/v{$cv->version_code}" : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
