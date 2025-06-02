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
     * Get all syllabus for this unit.
     */
    public function syllabus(): HasMany
    {
        return $this->hasMany(Syllabus::class);
    }

    /**
     * Get the active syllabus for this unit.
     */
    public function activeSyllabus()
    {
        return $this->hasOne(Syllabus::class)->where('is_active', true);
    }
}
