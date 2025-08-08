<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CurriculumUnit extends Model
{
    /** @use HasFactory<\Database\Factories\CurriculumUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'curriculum_version_id',
        'unit_id',
        'semester_id',
        'unit_scope',
        'year_level',
        'semester_number',
        'note',
    ];

    protected $casts = [
        'year_level' => 'integer',
        'semester_number' => 'integer',
    ];

    /**
     * Get the syllabus for this curriculum unit.
     */
    public function syllabus(): HasOne
    {
        return $this->hasOne(Syllabus::class, 'curriculum_unit_id', 'id');
    }

    /**
     * Get the curriculum version that owns this curriculum unit.
     */
    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    /**
     * Get the unit that belongs to this curriculum.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester for this curriculum unit.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Check if prerequisites are met for this curriculum unit.
     */
    public function checkPrerequisites(array $completedUnits = []): array
    {
        $unit = $this->unit;

        // Get all prerequisite groups for this unit
        $prerequisiteGroups = $unit->prerequisiteGroups()->with('conditions.requiredUnit')->get();

        $missingPrerequisites = [];
        $satisfiedPrerequisites = [];

        foreach ($prerequisiteGroups as $group) {
            foreach ($group->conditions as $condition) {
                if ($condition->requiredUnit) {
                    $isCompleted = in_array($condition->requiredUnit->id, $completedUnits);

                    if (! $isCompleted && ! $this->allows_concurrent_enrollment) {
                        $missingPrerequisites[] = [
                            'unit' => $condition->requiredUnit,
                            'type' => $condition->type,
                            'can_be_concurrent' => $condition->type === 'co_requisite' || $condition->type === 'concurrent_prerequisite',
                            'group_operator' => $group->logic_operator,
                        ];
                    } else {
                        $satisfiedPrerequisites[] = [
                            'unit' => $condition->requiredUnit,
                            'type' => $condition->type,
                            'group_operator' => $group->logic_operator,
                        ];
                    }
                }
            }
        }

        return [
            'can_enroll' => empty($missingPrerequisites),
            'missing_prerequisites' => $missingPrerequisites,
            'satisfied_prerequisites' => $satisfiedPrerequisites,
        ];
    }

    /**
     * Get the academic period for this unit (year-semester).
     */
    public function getAcademicPeriod(): ?string
    {
        if ($this->year_level && $this->semester_number) {
            return "Year {$this->year_level}, Semester {$this->semester_number}";
        }

        return null;
    }

    /**
     * Check if this is a core unit.
     */
    public function isCore(): bool
    {
        return $this->type === 'core';
    }

    /**
     * Check if this is a major unit.
     */
    public function isMajor(): bool
    {
        return $this->type === 'major';
    }

    /**
     * Check if this is an elective unit.
     */
    public function isElective(): bool
    {
        return $this->type === 'elective';
    }

    /**
     * Check if this unit is common across specializations.
     */
    public function isCommon(): bool
    {
        return $this->unit_scope === 'common';
    }

    /**
     * Check if this unit is specialization-specific.
     */
    public function isSpecializationSpecific(): bool
    {
        return $this->unit_scope === 'specialization_specific';
    }

    /**
     * Check if this unit is from another program (cross-program elective).
     */
    public function isCrossProgram(): bool
    {
        return $this->unit_scope === 'cross_program';
    }

    /**
     * Scope for units by group type.
     */
    public function scopeByGroupType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope for units by year level.
     */
    public function scopeByYearLevel(Builder $query, int $yearLevel): void
    {
        $query->where('year_level', $yearLevel);
    }

    /**
     * Scope for units by semester order.
     */
    public function scopeBySemesterOrder(Builder $query, int $semesterOrder): void
    {
        $query->where('semester_number', $semesterOrder);
    }

    /**
     * Scope for units by semester.
     */
    public function scopeBySemester(Builder $query, int $semesterNumber): void
    {
        $query->where('semester_number', $semesterNumber);
    }

    /**
     * Scope for common units across specializations.
     */
    public function scopeCommon(Builder $query): void
    {
        $query->where('unit_scope', 'common');
    }

    /**
     * Scope for specialization-specific units.
     */
    public function scopeSpecializationSpecific(Builder $query): void
    {
        $query->where('unit_scope', 'specialization_specific');
    }

    /**
     * Get all available elective units for this specialization.
     * Students can choose from all units across the university.
     */
    public function getAvailableElectiveUnits(): \Illuminate\Support\Collection
    {
        if (! $this->isElective()) {
            return collect();
        }

        $currentSpecializationId = $this->curriculumVersion->specialization_id;

        // Lấy tất cả units từ các chuyên ngành khác
        return Unit::query()
            ->whereHas('curriculumUnits', function ($query) use ($currentSpecializationId) {
                $query->whereHas('curriculumVersion', function ($q) use ($currentSpecializationId) {
                    // Units từ các chuyên ngành khác
                    $q->where('specialization_id', '!=', $currentSpecializationId)
                        ->orWhereNull('specialization_id'); // Hoặc units common
                });
            })
            ->orWhereDoesntHave('curriculumUnits') // Units chưa được assign
            ->distinct()
            ->get();
    }

    /**
     * Get all units from other specializations within the same program.
     */
    public function getUnitsFromOtherSpecializations(): \Illuminate\Support\Collection
    {
        $currentProgramId = $this->curriculumVersion->program_id;
        $currentSpecializationId = $this->curriculumVersion->specialization_id;

        return Unit::query()
            ->whereHas('curriculumUnits.curriculumVersion', function ($query) use ($currentProgramId, $currentSpecializationId) {
                $query->where('program_id', $currentProgramId)
                    ->where('specialization_id', '!=', $currentSpecializationId);
            })
            ->distinct()
            ->get();
    }

    /**
     * Get all units from other programs (cross-program electives).
     */
    public function getUnitsFromOtherPrograms(): \Illuminate\Support\Collection
    {
        $currentProgramId = $this->curriculumVersion->program_id;

        return Unit::query()
            ->whereHas('curriculumUnits.curriculumVersion', function ($query) use ($currentProgramId) {
                $query->where('program_id', '!=', $currentProgramId);
            })
            ->distinct()
            ->get();
    }

    /**
     * Get all units available as electives for this curriculum unit.
     */
    public function getAllAvailableElectives(): array
    {
        if (! $this->isElective()) {
            return [];
        }

        return [
            'same_program_other_specializations' => $this->getUnitsFromOtherSpecializations(),
            'other_programs' => $this->getUnitsFromOtherPrograms(),
            'unassigned_units' => Unit::whereDoesntHave('curriculumUnits')->get(),
        ];
    }

    /**
     * Check if this curriculum unit can be substituted with another unit.
     */
    public function canBeSubstitutedWith(Unit $unit): bool
    {
        if (! $this->isElective()) {
            return false;
        }

        // Basic validation - can be extended with more business rules
        return $unit->credit_points >= $this->unit->credit_points * 0.8; // At least 80% of credit points
    }
}
