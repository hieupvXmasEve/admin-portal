<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumVersion extends Model
{
    /** @use HasFactory<\Database\Factories\CurriculumVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'program_id',
        'specialization_id',
        'version_code',
        'semester_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
            ->join('unit_types', 'curriculum_units.unit_type_id', '=', 'unit_types.id')
            ->where('unit_types.name', 'elective')
            ->with(['unit', 'unitType'])
            ->select('curriculum_units.*')
            ->orderBy('year_level')
            ->orderBy('semester_number')
            ->get();
    }

    /**
     * Scope for active curriculum versions.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope for a specific program.
     */
    public function scopeForProgram(Builder $query, Program $program): void
    {
        $query->where('program_id', $program->id);
    }
}
