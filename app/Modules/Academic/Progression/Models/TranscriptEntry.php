<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Models;

use App\Models\AuditableModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class TranscriptEntry extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'course_result_id',
        'student_id',
        'course_offering_id',
        'semester_id',
        'unit_id',
        'program_id',
        'campus_id',
        'attempt_number',
        'final_percentage',
        'final_letter_grade',
        'credit_points',
        'credit_points_earned',
        'quality_points',
        'is_passed',
        'excluded_from_gpa',
        'affects_academic_standing',
        'affects_graduation_requirement',
        'satisfies_prerequisite',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'final_percentage' => 'decimal:2',
            'credit_points' => 'decimal:2',
            'credit_points_earned' => 'decimal:2',
            'quality_points' => 'decimal:2',
            'is_passed' => 'boolean',
            'excluded_from_gpa' => 'boolean',
            'affects_academic_standing' => 'boolean',
            'affects_graduation_requirement' => 'boolean',
            'satisfies_prerequisite' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function getCompletionStatusAttribute(): string
    {
        return $this->is_passed ? 'completed' : 'failed';
    }

    public function getGradeStatusAttribute(): string
    {
        return 'final';
    }

    public function getGradePointsAttribute(): float
    {
        $creditPoints = (float) ($this->credit_points ?? 0);

        if ($this->quality_points !== null && $creditPoints > 0) {
            return round((float) $this->quality_points / $creditPoints, 2);
        }

        return $this->is_passed ? 1.0 : 0.0;
    }

    public function getCompletionDateAttribute(): ?CarbonInterface
    {
        return $this->finalized_at;
    }

    public function getEnrollmentDateAttribute(): ?CarbonInterface
    {
        return $this->finalized_at;
    }
}
