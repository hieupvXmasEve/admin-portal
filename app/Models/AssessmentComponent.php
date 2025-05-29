<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'syllabus_id',
        'name',
        'weight',
        'type',
        'is_required_to_sit_final_exam',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_required_to_sit_final_exam' => 'boolean',
    ];

    /**
     * Available assessment types.
     */
    public const TYPES = [
        'quiz' => 'Quiz',
        'assignment' => 'Assignment',
        'project' => 'Project',
        'exam' => 'Exam',
        'online_activity' => 'Online Activity',
        'other' => 'Other',
    ];

    /**
     * Get the syllabus that owns this assessment component.
     */
    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(Syllabus::class);
    }

    /**
     * Get all details for this assessment component.
     */
    public function details(): HasMany
    {
        return $this->hasMany(AssessmentComponentDetail::class, 'component_id');
    }

    /**
     * Calculate the total weight of all component details.
     */
    public function getTotalDetailWeightAttribute(): float
    {
        return $this->details->sum('weight') ?? 0.0;
    }

    /**
     * Get the formatted type name.
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Check if this component has sub-details.
     */
    public function hasDetails(): bool
    {
        return $this->details()->exists();
    }
}
