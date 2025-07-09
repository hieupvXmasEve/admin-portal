<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicRecord extends Model
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
        'grade_status',
        'completion_status',
        'enrollment_date',
        'completion_date',
        'grade_submission_date',
        'grade_finalized_date',
        'attendance_percentage',
        'total_absences',
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
    ];

    protected $casts = [
        'final_percentage' => 'decimal:2',
        'grade_points' => 'decimal:2',
        'quality_points' => 'decimal:2',
        'credit_hours' => 'decimal:2',
        'credit_hours_earned' => 'decimal:2',
        'enrollment_date' => 'date',
        'completion_date' => 'date',
        'grade_submission_date' => 'date',
        'grade_finalized_date' => 'date',
        'attendance_percentage' => 'decimal:2',
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
    ];

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
        return $this->completion_status === 'completed' && $this->grade_points > 0;
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
        return !empty($this->final_letter_grade);
    }

    public function calculateQualityPoints(): float
    {
        return $this->grade_points * $this->credit_hours;
    }

    public function getEarnedCreditHours(): float
    {
        return $this->isPassed() ? $this->credit_hours : 0.0;
    }
}
