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
        'student_code',
        'semester_id',
        'program_id',
        'calculation_type',
        'gpa',
        'quality_points',
        'credit_hours_attempted',
        'credit_hours_earned',
        'credit_hours_gpa',
        'total_courses',
        'completed_courses',
        'failed_courses',
        'withdrawn_courses',
        'incomplete_courses',
        'a_grades',
        'b_grades',
        'c_grades',
        'd_grades',
        'f_grades',
        'academic_standing',
        'required_gpa',
        'meets_gpa_requirement',
        'gpa_deficit',
        'academic_year',
        'year_level',
        'semester_type',
        'class_rank',
        'class_size',
        'percentile',
        'program_rank',
        'program_class_size',
        'credits_needed_to_graduate',
        'completion_percentage',
        'projected_graduation_date',
        'on_track_to_graduate',
        'includes_transfer_credits',
        'includes_repeated_courses',
        'dean_list_eligible',
        'honors_eligible',
        'graduation_honors_eligible',
        'calculated_at',
        'calculated_by_lecture_id',
        'calculation_parameters',
        'calculation_notes',
        'is_verified',
        'verified_at',
        'verified_by_lecture_id',
        'is_current',
        'previous_gpa',
        'gpa_change',
        'gpa_trend',
    ];

    protected $casts = [
        'gpa' => 'decimal:3',
        'quality_points' => 'decimal:3',
        'credit_hours_attempted' => 'decimal:2',
        'credit_hours_earned' => 'decimal:2',
        'credit_hours_gpa' => 'decimal:2',
        'required_gpa' => 'decimal:2',
        'meets_gpa_requirement' => 'boolean',
        'gpa_deficit' => 'decimal:3',
        'percentile' => 'decimal:2',
        'credits_needed_to_graduate' => 'decimal:2',
        'completion_percentage' => 'decimal:2',
        'projected_graduation_date' => 'date',
        'on_track_to_graduate' => 'boolean',
        'includes_transfer_credits' => 'boolean',
        'includes_repeated_courses' => 'boolean',
        'dean_list_eligible' => 'boolean',
        'honors_eligible' => 'boolean',
        'graduation_honors_eligible' => 'boolean',
        'calculated_at' => 'datetime',
        'calculation_parameters' => 'array',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_current' => 'boolean',
        'previous_gpa' => 'decimal:3',
        'gpa_change' => 'decimal:3',
    ];

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'calculated_by_lecture_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'verified_by_lecture_id');
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeBySemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByCalculationType($query, $type)
    {
        return $query->where('calculation_type', $type);
    }

    public function scopeByAcademicStanding($query, $standing)
    {
        return $query->where('academic_standing', $standing);
    }

    public function scopeDeanListEligible($query)
    {
        return $query->where('dean_list_eligible', true);
    }

    public function scopeHonorsEligible($query)
    {
        return $query->where('honors_eligible', true);
    }

    public function scopeOnProbation($query)
    {
        return $query->where('academic_standing', 'probation');
    }

    // Helper methods
    public function isExcellent(): bool
    {
        return $this->academic_standing === 'excellent';
    }

    public function isOnProbation(): bool
    {
        return $this->academic_standing === 'probation';
    }

    public function isDeanListEligible(): bool
    {
        return $this->dean_list_eligible;
    }

    public function isHonorsEligible(): bool
    {
        return $this->honors_eligible;
    }

    public function meetsGpaRequirement(): bool
    {
        return $this->meets_gpa_requirement;
    }

    public function getGpaLevel(): string
    {
        if ($this->gpa >= 3.5) return 'excellent';
        if ($this->gpa >= 3.0) return 'good';
        if ($this->gpa >= 2.5) return 'satisfactory';
        if ($this->gpa >= 2.0) return 'probation';
        return 'poor';
    }

    public function getCompletionRate(): float
    {
        return $this->total_courses > 0
            ? ($this->completed_courses / $this->total_courses) * 100
            : 0.0;
    }

    public function getSuccessRate(): float
    {
        return $this->credit_hours_attempted > 0
            ? ($this->credit_hours_earned / $this->credit_hours_attempted) * 100
            : 0.0;
    }

    public function calculateGpaFromQualityPoints(): float
    {
        return $this->credit_hours_gpa > 0
            ? $this->quality_points / $this->credit_hours_gpa
            : 0.0;
    }

    public function getRankingSuffix(): string
    {
        if (!$this->class_rank) return '';

        $rank = $this->class_rank;
        if ($rank % 100 >= 11 && $rank % 100 <= 13) return 'th';

        return match ($rank % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th'
        };
    }

    public function getFormattedRank(): string
    {
        if (!$this->class_rank || !$this->class_size) return 'N/A';

        return $this->class_rank . $this->getRankingSuffix() . ' of ' . $this->class_size;
    }
}
