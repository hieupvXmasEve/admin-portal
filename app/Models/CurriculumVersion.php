<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumVersion extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\CurriculumVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'program_id',
        'specialization_id',
        'version_code',
        'semester_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the program that owns the curriculum version.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the specialization that owns this curriculum version.
     */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    /**
     * Get the semester from which this curriculum version is effective.
     */
    public function effectiveFromSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Get the curriculum units for this version.
     */
    public function curriculumUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class);
    }

    /**
     * Get required/compulsory curriculum units.
     */
    public function requiredUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('is_compulsory', true);
    }

    /**
     * Get elective curriculum units.
     */
    public function electiveUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('is_compulsory', false);
    }

    /**
     * Get students enrolled with this curriculum version.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Get students enrolled with this curriculum version.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the curriculum modules for this version.
     */
    public function curriculumModules(): HasMany
    {
        return $this->hasMany(CurriculumModule::class);
    }

    /**
     * Get modules through curriculum_modules pivot.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'curriculum_modules')
            ->withPivot('year_level', 'semester_number', 'is_required', 'group_name', 'order', 'note')
            ->withTimestamps()
            ->orderBy('curriculum_modules.order');
    }

    /**
     * Check if this curriculum uses modular structure.
     */
    public function hasModularStructure(): bool
    {
        return $this->curriculumModules()->exists();
    }

    /**
     * Get curriculum units by group type.
     */
    public function unitsByGroupType(string $type): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('type', $type);
    }

    /**
     * Get curriculum units by year level.
     */
    public function unitsByYearLevel(int $yearLevel): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('year_level', $yearLevel);
    }

    /**
     * Scope for a specific specialization.
     */
    public function scopeForSpecialization(Builder $query, Specialization $specialization): void
    {
        $query->where('specialization_id', $specialization->id);
    }

    /**
     * Get all available elective units for this curriculum version.
     * Students can choose from all units outside their specialization.
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getAvailableElectiveUnits(): \Illuminate\Support\Collection
    {
        // Optimize with raw queries to avoid nested whereHas
        $excludedUnitIds = CurriculumUnit::query()
            ->whereHas('curriculumVersion', function ($query) {
                $query->where('program_id', $this->program_id)
                      ->where('specialization_id', $this->specialization_id);
            })
            ->pluck('unit_id')
            ->toArray();

        return Unit::query()
            ->whereNotIn('id', $excludedUnitIds)
            ->with(['unitType', 'prerequisites']) // Eager load commonly used relations
            ->orderBy('code')
            ->get();
    }

    /**
     * Get units by category for elective selection.
     */
    public function getElectiveUnitsByCategory(): array
    {
        $sameProgram = Unit::whereHas('curriculumUnits.curriculumVersion', function ($query) {
            $query->where('program_id', $this->program_id)
                ->where('specialization_id', '!=', $this->specialization_id);
        })->distinct()->get();

        $otherPrograms = Unit::whereHas('curriculumUnits.curriculumVersion', function ($query) {
            $query->where('program_id', '!=', $this->program_id);
        })->distinct()->get();

        $unassigned = Unit::whereDoesntHave('curriculumUnits')->get();

        return [
            'same_program_other_specializations' => $sameProgram,
            'cross_program_electives' => $otherPrograms,
            'general_electives' => $unassigned,
        ];
    }

    /**
     * Get elective slots for this curriculum version.
     */
    public function getElectiveSlots(): \Illuminate\Support\Collection
    {
        return $this->curriculumUnits()
            ->where('curriculum_units.type', 'elective')
            ->with('unit')
            ->select('curriculum_units.*')
            ->orderBy('year_level')
            ->orderBy('semester_number')
            ->get();
    }

    /**
     * Scope for active curriculum versions based on having curriculum units.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereHas('curriculumUnits');
    }

    /**
     * Scope for a specific program.
     */
    public function scopeForProgram(Builder $query, Program $program): void
    {
        $query->where('program_id', $program->id);
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for curriculum versions (critical academic data)
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
            'specialization_id',
            'version_code',
            'semester_id',
            'notes',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $programName = $this->program?->name ?? "Program ID {$this->program_id}";
        $specializationName = $this->specialization?->name ?? 'General';
        $versionCode = $this->version_code;

        return "{$programName} - {$specializationName} (v{$versionCode})";
    }

    /**
     * Custom activity descriptions for curriculum version events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created curriculum version: {$identifier}",
            'updated' => "Updated curriculum version: {$identifier}",
            'deleted' => "Archived curriculum version: {$identifier}",
            'restored' => "Restored curriculum version: {$identifier}",
            default => "{$eventName} curriculum version: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'program_code' => $this->program?->code,
            'program_name' => $this->program?->name,
            'specialization_code' => $this->specialization?->code,
            'specialization_name' => $this->specialization?->name ?? 'General Program',
            'version_code' => $this->version_code,
            'effective_from_semester' => $this->effectiveFromSemester?->code,
            'total_units' => $this->curriculumUnits()->count(),
            'required_units' => $this->requiredUnits()->count(),
            'elective_units' => $this->electiveUnits()->count(),
            'affected_students_count' => $this->students()->count(),
            'active_enrollments_count' => $this->enrollments()->where('status', 'in_progress')->count(),
        ];


        // Track version changes
        if ($this->isDirty('version_code') && $this->exists) {
            $properties['version_change'] = [
                'from_version' => $this->getOriginal('version_code'),
                'to_version' => $this->version_code,
                'change_type' => 'version_update',
            ];
        }

        // Track semester effectiveness change
        if ($this->isDirty('semester_id') && $this->exists) {
            $oldSemester = Semester::find($this->getOriginal('semester_id'));
            $newSemester = Semester::find($this->semester_id);
            
            $properties['effectiveness_change'] = [
                'from_semester' => $oldSemester?->code,
                'to_semester' => $newSemester?->code,
                'impact' => 'curriculum_applicability_changed',
            ];
        }

        // Add curriculum structure summary
        $properties['curriculum_structure'] = $this->getCurriculumStructureSummary();

        return $properties;
    }

    /**
     * Get curriculum structure summary for logging
     */
    private function getCurriculumStructureSummary(): array
    {
        $unitsByYear = [];
        for ($year = 1; $year <= 4; $year++) {
            $unitsByYear["year_{$year}"] = $this->unitsByYearLevel($year)->count();
        }

        return [
            'by_year' => $unitsByYear,
            'by_type' => [
                'core' => $this->unitsByGroupType('core')->count(),
                'elective' => $this->unitsByGroupType('elective')->count(),
                'specialization' => $this->unitsByGroupType('specialization')->count(),
            ],
            'total_credit_points' => $this->curriculumUnits()
                ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
                ->sum('units.credit_points'),
        ];
    }
}
