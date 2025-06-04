<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumUnitType extends Model
{
    /** @use HasFactory<\Database\Factories\CurriculumUnitTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'name' => 'string',
    ];

    /**
     * Get the curriculum units of this type.
     */
    public function curriculumUnits(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class, 'unit_type_id');
    }

    /**
     * Check if this is a core unit type.
     */
    public function isCore(): bool
    {
        return $this->name === 'core';
    }

    /**
     * Check if this is an elective unit type.
     */
    public function isElective(): bool
    {
        return $this->name === 'elective';
    }

    /**
     * Check if this is a major unit type.
     */
    public function isMajor(): bool
    {
        return $this->name === 'major';
    }
}
