<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicRecord extends AuditableModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'course_offering_id',
        'semester_id',
        'unit_id',
        'program_id',
        'campus_id',
        'final_percentage',
        'final_letter_grade',
        'grade_points',
        'quality_points',
        'credit_hours',
        'credit_hours_earned',
        'credit_points',
        'credit_points_earned',
        'grade_status',
        'completion_status',
        'enrollment_date',
        'completion_date',
        'grade_submission_date',
        'grade_finalized_date',
        'attendance_percentage',
        'total_absences',
        'total_present',
        'total_late',
        'total_not_recorded',
        'total_class_sessions',
        'meets_attendance_requirement',
        'is_repeat_course',
        'attempt_number',
        'original_record_id',
        'is_transfer_credit',
        'transfer_institution',
        'transfer_course_code',
        'transfer_course_title',
        'is_advanced_placement',
        'is_challenge_exam',
        'is_credit_by_exam',
        'grade_breakdown',
        'raw_percentage',
        'curve_adjustment',
        'grade_adjustment_reason',
        'excluded_from_gpa',
        'gpa_exclusion_reason',
        'instructor_comments',
        'administrative_notes',
        'instructor_id',
        'grade_submitted_by_lecture_id',
        'grade_approved_by_lecture_id',
        'affects_academic_standing',
        'affects_graduation_requirement',
        'satisfies_prerequisite',
        'grade_history',
        'last_grade_change_at',
        'last_changed_by_lecture_id',
        'override_pass',
        'is_passed',
        'override_reason',
    ];

    /**
     * Configure activity logging for academic records
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE; // Critical for academic records
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $student = $this->student;
        $unit = $this->unit;
        $semester = $this->semester;

        if ($student && $unit && $semester) {
            return "Academic Record - Student: {$student->student_id} ({$student->full_name}) - Unit: {$unit->code} - Semester: {$semester->name}";
        }

        return "Academic Record ID {$this->getKey()}";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Academic record created: {$identifier}",
            'updated' => "Academic record updated: {$identifier}",
            'deleted' => "Academic record deleted: {$identifier}",
            'restored' => "Academic record restored: {$identifier}",
            default => "{$eventName} academic record: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    public function getExtraLogProperties(): array
    {
        return [
            'student_id' => $this->student_id,
            'course_offering_id' => $this->course_offering_id,
            'semester_id' => $this->semester_id,
            'unit_id' => $this->unit_id,
            'program_id' => $this->program_id,
            'campus_id' => $this->campus_id,
            'final_percentage' => $this->final_percentage,
            'final_letter_grade' => $this->final_letter_grade,
            'grade_points' => $this->grade_points,
            'completion_status' => $this->completion_status,
            'is_transfer_credit' => $this->is_transfer_credit,
            'is_repeat_course' => $this->is_repeat_course,
            'affects_graduation_requirement' => $this->affects_graduation_requirement,
            'excluded_from_gpa' => $this->excluded_from_gpa,
            'override_pass' => $this->override_pass,
            'override_reason' => $this->override_reason,
        ];
    }

    protected $casts = [
        'final_percentage' => 'decimal:2',
        'grade_points' => 'decimal:2',
        'quality_points' => 'decimal:2',
        'credit_hours',
        'credit_hours_earned',
        'credit_points' => 'decimal:2',
        'credit_points_earned' => 'decimal:2',
        'enrollment_date' => 'date',
        'completion_date' => 'date',
        'grade_submission_date' => 'date',
        'grade_finalized_date' => 'date',
        'attendance_percentage' => 'decimal:2',
        'total_absences' => 'integer',
        'total_present' => 'integer',
        'total_late' => 'integer',
        'total_not_recorded' => 'integer',
        'total_class_sessions' => 'integer',
        'meets_attendance_requirement' => 'boolean',
        'is_repeat_course' => 'boolean',
        'is_transfer_credit' => 'boolean',
        'is_advanced_placement' => 'boolean',
        'is_challenge_exam' => 'boolean',
        'is_credit_by_exam' => 'boolean',
        'grade_breakdown' => 'array',
        'raw_percentage' => 'decimal:2',
        'curve_adjustment' => 'decimal:2',
        'excluded_from_gpa' => 'boolean',
        'affects_academic_standing' => 'boolean',
        'affects_graduation_requirement' => 'boolean',
        'satisfies_prerequisite' => 'boolean',
        'grade_history' => 'array',
        'last_grade_change_at' => 'datetime',
        'override_pass' => 'boolean',
        'is_passed' => 'boolean',
    ];

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'instructor_id');
    }

    public function gradeSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'grade_submitted_by_lecture_id');
    }

    public function gradeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'grade_approved_by_lecture_id');
    }

    public function lastChangedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'last_changed_by_lecture_id');
    }

    public function originalRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class, 'original_record_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('completion_status', 'completed');
    }

    public function scopeFinalGrades($query)
    {
        return $query->where('grade_status', 'final');
    }

    public function scopeForGPA($query)
    {
        return $query->where('excluded_from_gpa', false)
            ->whereIn('completion_status', ['completed', 'failed']);
    }

    public function scopeTransferCredits($query)
    {
        return $query->where('is_transfer_credit', true);
    }

    public function scopeRepeatCourses($query)
    {
        return $query->where('is_repeat_course', true);
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->completion_status === 'completed';
    }

    public function isPassed(): bool
    {
        // Consider override_pass flag or normal passing criteria
        return $this->override_pass || ($this->completion_status === 'completed' && $this->grade_points > 0);
    }

    public function isFailed(): bool
    {
        return $this->completion_status === 'failed';
    }

    public function isInProgress(): bool
    {
        return $this->completion_status === 'in_progress';
    }

    public function hasLetterGrade(): bool
    {
        return ! empty($this->final_letter_grade);
    }

    public function calculateQualityPoints(): float
    {
        return $this->grade_points * $this->credit_hours;
    }

    public function getEarnedCreditHours(): float
    {
        return $this->isPassed() ? $this->credit_hours : 0.0;
    }

    public function isOverridden(): bool
    {
        return (bool) $this->override_pass;
    }

    /**
     * Calculate grade points from final percentage using a continuous formula on a 4.0 scale.
     * This avoids rigid "steps" and provides a more precise representation of the score.
     */
    public static function calculateGradePoints(float $percentage): float
    {
        // Map percentage to 4.0 scale:
        // A common accurate formula for continuous GPA is (Percentage / 100) * 4
        // However, many systems cap 4.0 at 90% or 95%. 
        // We will use min(4.0, (percentage / 25)) if we want 100% = 4.0 linear
        // Or min(4.0, round(($percentage * 4) / 100, 2)) for a direct ratio.
        
        $gpa = ($percentage * 4) / 100;

        return round(max(0, min(4.0, $gpa)), 2);
    }

    /**
     * Convert percentage to letter grade
     */
    public static function calculateLetterGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'A+',
            $percentage >= 85 => 'A',
            $percentage >= 80 => 'A-',
            $percentage >= 75 => 'B+',
            $percentage >= 70 => 'B',
            $percentage >= 65 => 'B-',
            $percentage >= 60 => 'C+',
            $percentage >= 55 => 'C',
            $percentage >= 50 => 'C-',
            default => 'F',
        };
    }
}
