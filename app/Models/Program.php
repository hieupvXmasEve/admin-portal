<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    /** @use HasFactory<\Database\Factories\ProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'degree_level',
    ];

    protected $casts = [
        'degree_level' => 'string',
    ];

    /**
     * Get the curriculum versions for the program.
     */
    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }

    /**
     * Get the specializations for the program.
     */
    public function specializations(): HasMany
    {
        return $this->hasMany(Specialization::class);
    }

    /**
     * Get only active specializations for the program.
     */
    public function activeSpecializations(): HasMany
    {
        return $this->hasMany(Specialization::class)->where('is_active', true);
    }

    /**
     * Get program-level curriculum versions (common to all specializations).
     */
    public function programCurriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class)->where('scope', 'program');
    }

    /**
     * Get specialization-level curriculum versions.
     */
    public function specializationCurriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class)->where('scope', 'specialization');
    }
}
