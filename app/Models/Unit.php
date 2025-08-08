<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
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
}
