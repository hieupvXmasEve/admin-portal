<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialization extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\SpecializationFactory> */
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the program that owns this specialization.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the curriculum versions for this specialization.
     */
    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }

    /**
     * Get the active curriculum version for this specialization.
     */
    public function activeCurriculumVersion(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class)
            ->latest('semester_id');
    }

    /**
     * Get all units for this specialization (including common program units).
     */
    public function getAllUnits()
    {
        // Get program-level common units
        $programUnits = $this->program->curriculumVersions()
            ->with('curriculumUnits.unit')
            ->get()
            ->flatMap(function ($curriculum) {
                return $curriculum->curriculumUnits->map(function ($curriculumUnit) {
                    return [
                        'unit' => $curriculumUnit->unit,
                        'type' => $curriculumUnit->type,
                        'unit_scope' => $curriculumUnit->unit_scope,
                        'is_required' => $curriculumUnit->is_required,
                        'year_level' => $curriculumUnit->year_level,
                        'semester_number' => $curriculumUnit->semester_number,
                        'source' => 'program',
                    ];
                });
            });

        // Get specialization-specific units
        $specializationUnits = $this->curriculumVersions()
            ->with('curriculumUnits.unit')
            ->get()
            ->flatMap(function ($curriculum) {
                return $curriculum->curriculumUnits->map(function ($curriculumUnit) {
                    return [
                        'unit' => $curriculumUnit->unit,
                        'type' => $curriculumUnit->type,
                        'unit_scope' => $curriculumUnit->unit_scope,
                        'is_required' => $curriculumUnit->is_required,
                        'year_level' => $curriculumUnit->year_level,
                        'semester_number' => $curriculumUnit->semester_number,
                        'source' => 'specialization',
                    ];
                });
            });

        return $programUnits->concat($specializationUnits);
    }

    /**
     * Check if a unit is available in this specialization and get its role.
     */
    public function getUnitRole(Unit $unit): ?array
    {
        $allUnits = $this->getAllUnits();

        $unitRoles = $allUnits->filter(function ($item) use ($unit) {
            return $item['unit']->id === $unit->id;
        });

        return $unitRoles->first();
    }

    /**
     * Scope a query to only include active specializations.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to include specializations for a specific program.
     */
    public function scopeForProgram(Builder $query, Program $program): void
    {
        $query->where('program_id', $program->id);
    }

    /**
     * Get validation rules for creating/updating specializations.
     */
    public static function validationRules(?int $id = null): array
    {
        return [
            'program_id' => ['required', 'exists:programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:specializations,code'.($id ? ",$id" : '')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get validation messages for specializations.
     */
    public static function validationMessages(): array
    {
        return [
            'program_id.required' => 'Program is required',
            'program_id.exists' => 'Selected program does not exist',
            'name.required' => 'Specialization name is required',
            'name.max' => 'Specialization name cannot exceed 255 characters',
            'code.required' => 'Specialization code is required',
            'code.max' => 'Specialization code cannot exceed 50 characters',
            'code.unique' => 'This specialization code is already taken',
            'description.max' => 'Description cannot exceed 1000 characters',
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for specializations (critical academic structure)
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
            'program_id',
            'name',
            'code',
            'description',
            'is_active',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $programName = $this->program?->name ?? "Program ID {$this->program_id}";
        return "{$this->name} ({$this->code}) - {$programName}";
    }

    /**
     * Custom activity descriptions for specialization events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created specialization: {$identifier}",
            'updated' => "Updated specialization: {$identifier}",
            'deleted' => "Removed specialization: {$identifier}",
            'restored' => "Restored specialization: {$identifier}",
            default => "{$eventName} specialization: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'specialization_code' => $this->code,
            'specialization_name' => $this->name,
            'program_code' => $this->program?->code,
            'program_name' => $this->program?->name,
            'is_active' => $this->is_active,
            'curriculum_versions_count' => $this->curriculumVersions()->count(),
            'active_curriculum_versions' => $this->curriculumVersions()->where('is_active', true)->count(),
            'total_students' => $this->getStudentCount(),
            'active_students' => $this->getActiveStudentCount(),
        ];

        // Track activation/deactivation
        if ($this->isDirty('is_active') && $this->exists) {
            $properties['activation_change'] = [
                'from' => $this->getOriginal('is_active') ? 'active' : 'inactive',
                'to' => $this->is_active ? 'active' : 'inactive',
                'changed_at' => now()->toDateTimeString(),
                'impact' => [
                    'affected_curriculum_versions' => $this->curriculumVersions()->pluck('version_code')->toArray(),
                    'affected_students' => $this->getAffectedStudentIds(),
                ],
            ];
        }

        // Track program change (shouldn't normally happen, but critical if it does)
        if ($this->isDirty('program_id') && $this->exists) {
            $oldProgram = Program::find($this->getOriginal('program_id'));
            $newProgram = Program::find($this->program_id);
            
            $properties['program_change'] = [
                'from_program' => $oldProgram?->name,
                'to_program' => $newProgram?->name,
                'change_type' => 'program_transfer',
                'requires_curriculum_review' => true,
            ];
        }

        // Add specialization structure details
        $properties['specialization_structure'] = $this->getSpecializationStructure();

        return $properties;
    }

    /**
     * Get total student count for this specialization
     */
    private function getStudentCount(): int
    {
        return Student::where('specialization_id', $this->id)->count();
    }

    /**
     * Get active student count for this specialization
     */
    private function getActiveStudentCount(): int
    {
        return Student::where('specialization_id', $this->id)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get IDs of students affected by specialization changes
     */
    private function getAffectedStudentIds(): array
    {
        return Student::where('specialization_id', $this->id)
            ->whereIn('status', ['active', 'suspended'])
            ->pluck('student_id')
            ->toArray();
    }

    /**
     * Get specialization structure summary
     */
    private function getSpecializationStructure(): array
    {
        $allUnits = $this->getAllUnits();
        
        return [
            'total_units' => $allUnits->count(),
            'program_units' => $allUnits->where('source', 'program')->count(),
            'specialization_units' => $allUnits->where('source', 'specialization')->count(),
            'required_units' => $allUnits->where('is_required', true)->count(),
            'elective_units' => $allUnits->where('is_required', false)->count(),
            'units_by_year' => $allUnits->groupBy('year_level')->map->count()->toArray(),
        ];
    }
}
