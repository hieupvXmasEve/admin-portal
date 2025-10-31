<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumModule extends Model
{
    protected $fillable = [
        'curriculum_version_id',
        'module_id',
        'year_level',
        'semester_number',
        'is_required',
        'group_name',
        'order',
        'note',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'year_level' => 'integer',
        'semester_number' => 'integer',
        'order' => 'integer',
    ];

    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
