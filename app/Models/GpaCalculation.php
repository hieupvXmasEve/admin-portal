<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GpaCalculation extends AuditableModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'semester_id',
        'program_id',
        'semester_gpa',
        'cumulative_gpa',
        'semester_quality_points',
        'cumulative_quality_points',
        'semester_credit_points',
        'cumulative_credit_points',
        'semester_credit_points_earned',
        'cumulative_credit_points_earned',
        'academic_standing',
        'is_finalized',
        'finalized_at',
        'finalized_by_id',
        'remarks',
        'is_current',
    ];

    protected $casts = [
        'semester_gpa' => 'decimal:3',
        'cumulative_gpa' => 'decimal:3',
        'semester_quality_points' => 'decimal:3',
        'cumulative_quality_points' => 'decimal:3',
        'semester_credit_points' => 'decimal:2',
        'cumulative_credit_points' => 'decimal:2',
        'semester_credit_points_earned' => 'decimal:2',
        'cumulative_credit_points_earned' => 'decimal:2',
        'is_finalized' => 'boolean',
        'finalized_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_id');
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeFinalized($query)
    {
        return $query->where('is_finalized', true);
    }

    public function scopeBySemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    // Helper methods
    public function isNormal(): bool
    {
        return $this->academic_standing === 'normal';
    }

    public function isWarning(): bool
    {
        return $this->academic_standing === 'warning';
    }

    public function getSemesterCompletionRate(): float
    {
        return $this->semester_credit_points > 0
            ? ($this->semester_credit_points_earned / $this->semester_credit_points) * 100
            : 0.0;
    }

    public function getCumulativeCompletionRate(): float
    {
        return $this->cumulative_credit_points > 0
            ? ($this->cumulative_credit_points_earned / $this->cumulative_credit_points) * 100
            : 0.0;
    }
}
