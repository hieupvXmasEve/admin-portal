<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

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
     */
    public function getAvailableElectiveUnits(): \Illuminate\Support\Collection
    {
        // Lấy tất cả units từ:
        // 1. Các chuyên ngành khác trong cùng program
        // 2. Tất cả units từ các programs khác
        // 3. Units chưa được assign vào curriculum nào

        return Unit::query()
            ->where(function ($query) {
                // Units từ các chuyên ngành khác trong cùng program
                $query->whereHas('curriculumUnits.curriculumVersion', function ($q) {
                    $q->where('program_id', $this->program_id)
                        ->where('specialization_id', '!=', $this->specialization_id);
                })
                    // Hoặc units từ programs khác
                    ->orWhereHas('curriculumUnits.curriculumVersion', function ($q) {
                        $q->where('program_id', '!=', $this->program_id);
                    })
                    // Hoặc units chưa được assign
                    ->orWhereDoesntHave('curriculumUnits');
            })
            ->distinct()
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
            ->whereHas('unitType', function ($query) {
                $query->where('name', 'elective');
            })
            ->with(['unit', 'unitType'])
            ->orderBy('year_level')
            ->orderBy('semester_number')
            ->get();
    }
}
