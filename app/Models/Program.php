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
        'code',
        'description'
    ];

    protected $casts = [
        'code' => 'string',
        'description' => 'string',
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
}
