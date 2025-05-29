<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Specialization extends Model
{
    /** @use HasFactory<\Database\Factories\SpecializationFactory> */
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'code',
        'description',
        'is_active',
        'duration_years',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_years' => 'integer',
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
            ->where('scope', 'specialization')
            ->latest('effective_from_semester_id');
    }

    /**
     * Get all units for this specialization (including common program units).
     */
    public function getAllUnits()
    {
        // Get program-level common units
        $programUnits = $this->program->curriculumVersions()
            ->where('scope', 'program')
            ->with('curriculumUnits.unit')
            ->get()
            ->flatMap(function ($curriculum) {
                return $curriculum->curriculumUnits->map(function ($curriculumUnit) {
                    return [
                        'unit' => $curriculumUnit->unit,
                        'group_type' => $curriculumUnit->group_type,
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
            ->where('scope', 'specialization')
            ->with('curriculumUnits.unit')
            ->get()
            ->flatMap(function ($curriculum) {
                return $curriculum->curriculumUnits->map(function ($curriculumUnit) {
                    return [
                        'unit' => $curriculumUnit->unit,
                        'group_type' => $curriculumUnit->group_type,
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
}
