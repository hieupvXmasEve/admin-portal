<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicStanding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'semester_id',
        'standing',
        'gpa',
        'cumulative_gpa',
        'total_credits_completed',
        'reason',
        'notes',
        'is_active',
        'effective_date',
        'created_by',
    ];

    protected $casts = [
        'gpa' => 'decimal:2',
        'cumulative_gpa' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_date' => 'datetime',
    ];

    public static function validationRules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'standing' => ['required', 'in:good,probation,suspension,honors'],
            'gpa' => ['required', 'numeric', 'min:0', 'max:4'],
            'cumulative_gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'total_credits_completed' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'effective_date' => ['required', 'date'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'semester_id.required' => 'Semester is required',
            'standing.required' => 'Academic standing is required',
            'gpa.required' => 'GPA is required',
            'gpa.min' => 'GPA cannot be negative',
            'gpa.max' => 'GPA cannot exceed 4.0',
            'effective_date.required' => 'Effective date is required',
        ];
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStanding($query, string $standing)
    {
        return $query->where('standing', $standing);
    }

    public function scopeGood($query)
    {
        return $query->where('standing', 'good');
    }

    public function scopeProbation($query)
    {
        return $query->where('standing', 'probation');
    }

    public function scopeSuspension($query)
    {
        return $query->where('standing', 'suspension');
    }

    public function scopeHonors($query)
    {
        return $query->where('standing', 'honors');
    }

    // Helper methods
    public function isGoodStanding(): bool
    {
        return $this->standing === 'good';
    }

    public function isProbation(): bool
    {
        return $this->standing === 'probation';
    }

    public function isSuspension(): bool
    {
        return $this->standing === 'suspension';
    }

    public function isHonors(): bool
    {
        return $this->standing === 'honors';
    }

    public function getStandingColorAttribute(): string
    {
        return match ($this->standing) {
            'good' => 'green',
            'probation' => 'yellow',
            'suspension' => 'red',
            'honors' => 'blue',
            default => 'gray',
        };
    }

    public function getStandingLabelAttribute(): string
    {
        return match ($this->standing) {
            'good' => 'Good Standing',
            'probation' => 'Academic Probation',
            'suspension' => 'Academic Suspension',
            'honors' => 'Honors',
            default => 'Unknown',
        };
    }
}
