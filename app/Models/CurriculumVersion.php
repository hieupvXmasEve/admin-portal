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
        'effective_from_semester_id',
        'scope',
        'notes',
    ];

    protected $casts = [
        'scope' => 'string',
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
        return $this->belongsTo(Semester::class, 'effective_from_semester_id');
    }

    /**
     * Get the curriculum units for this version.
     */
    public function curriculumUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class);
    }

    /**
     * Get required curriculum units.
     */
    public function requiredUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('is_required', true);
    }

    /**
     * Get elective curriculum units.
     */
    public function electiveUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('is_required', false);
    }

    /**
     * Get curriculum units by group type.
     */
    public function unitsByGroupType(string $groupType): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('group_type', $groupType);
    }

    /**
     * Get curriculum units by year level.
     */
    public function unitsByYearLevel(int $yearLevel): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->where('year_level', $yearLevel);
    }

    /**
     * Scope for program-level curricula (common to all specializations).
     */
    public function scopeProgramLevel(Builder $query): void
    {
        $query->where('scope', 'program');
    }

    /**
     * Scope for specialization-specific curricula.
     */
    public function scopeSpecializationLevel(Builder $query): void
    {
        $query->where('scope', 'specialization');
    }

    /**
     * Scope for a specific specialization.
     */
    public function scopeForSpecialization(Builder $query, Specialization $specialization): void
    {
        $query->where('specialization_id', $specialization->id);
    }

    /**
     * Check if this curriculum version is program-level (common).
     */
    public function isProgramLevel(): bool
    {
        return $this->scope === 'program';
    }

    /**
     * Check if this curriculum version is specialization-specific.
     */
    public function isSpecializationLevel(): bool
    {
        return $this->scope === 'specialization';
    }
}
