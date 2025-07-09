<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentComponentDetailScore extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_component_detail_id',
        'student_id',
        'course_offering_id',
        'graded_by_lecture_id',
        'points_earned',
        'percentage_score',
        'letter_grade',
        'gpa_points',
        'submitted_at',
        'graded_at',
        'submission_attempt',
        'submission_files',
        'submission_text',
        'submission_url',
        'is_late',
        'minutes_late',
        'late_penalty_applied',
        'late_excuse',
        'late_excuse_approved',
        'status',
        'score_status',
        'instructor_feedback',
        'private_notes',
        'rubric_scores',
        'bonus_points',
        'bonus_reason',
        'student_group_id',
        'individual_score_override',
        'individual_override_reason',
        'plagiarism_suspected',
        'plagiarism_score',
        'plagiarism_notes',
        'integrity_status',
        'score_history',
        'last_modified_at',
        'last_modified_by_lecture_id',
        'is_extra_credit',
        'is_makeup',
        'special_circumstances',
        'score_excluded',
        'exclusion_reason',
        'student_comments',
        'appeal_requested',
        'appeal_requested_at',
        'appeal_reason',
        'appeal_status',
    ];

    protected $casts = [
        'points_earned' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'gpa_points' => 'decimal:2',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'submission_files' => 'array',
        'is_late' => 'boolean',
        'late_penalty_applied' => 'decimal:2',
        'late_excuse_approved' => 'boolean',
        'rubric_scores' => 'array',
        'bonus_points' => 'decimal:2',
        'individual_score_override' => 'boolean',
        'plagiarism_suspected' => 'boolean',
        'plagiarism_score' => 'decimal:2',
        'score_history' => 'array',
        'last_modified_at' => 'datetime',
        'is_extra_credit' => 'boolean',
        'is_makeup' => 'boolean',
        'score_excluded' => 'boolean',
        'appeal_requested' => 'boolean',
        'appeal_requested_at' => 'datetime',
    ];

    // Relationships
    public function assessmentComponentDetail(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponentDetail::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'graded_by_lecture_id');
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'last_modified_by_lecture_id');
    }

    // public function studentGroup(): BelongsTo
    // {
    //     return $this->belongsTo(StudentGroup::class);
    // }

    // Scopes
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    public function scopeFinalScores($query)
    {
        return $query->where('score_status', 'final');
    }

    public function scopeNotExcluded($query)
    {
        return $query->where('score_excluded', false);
    }

    // Helper methods
    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'grading', 'graded', 'returned']);
    }

    public function isGraded(): bool
    {
        return in_array($this->status, ['graded', 'returned']);
    }

    public function isLate(): bool
    {
        return $this->is_late;
    }

    public function hasBonus(): bool
    {
        return $this->bonus_points > 0;
    }

    public function isPlagiarismSuspected(): bool
    {
        return $this->plagiarism_suspected;
    }

    public function hasAppeal(): bool
    {
        return $this->appeal_requested;
    }

    public function calculateFinalScore(): float
    {
        $score = $this->percentage_score ?? 0;

        if ($this->is_late && $this->late_penalty_applied > 0) {
            $score -= $this->late_penalty_applied;
        }

        if ($this->bonus_points > 0) {
            $score += $this->bonus_points;
        }

        return max(0, min(100, $score));
    }
}
