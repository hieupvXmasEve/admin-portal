<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentComponentDetailScore extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_component_detail_id',
        'student_code',
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

    public function calculateFinalScore(): ?float
    {
        // Return null if score is excluded from calculations
        if ($this->score_excluded) {
            return null;
        }

        $score = $this->percentage_score ?? 0;

        // Apply late penalty only if not excused
        if ($this->is_late && $this->late_penalty_applied > 0 && !$this->late_excuse_approved) {
            $score -= $this->late_penalty_applied;
        }

        // Add bonus points
        if ($this->bonus_points > 0) {
            $score += $this->bonus_points;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate late penalty based on submission time and assessment deadline.
     *
     * @param \Carbon\Carbon $submissionTime
     * @param \Carbon\Carbon $deadline
     * @param array $penaltyRules
     * @return float Penalty percentage to apply
     */
    public function calculateLatePenalty(\Carbon\Carbon $submissionTime, \Carbon\Carbon $deadline, array $penaltyRules = []): float
    {
        if ($submissionTime->lte($deadline)) {
            return 0.0;
        }

        $minutesLate = $submissionTime->diffInMinutes($deadline);

        // Get penalty rules from assessment component or use defaults
        $penaltyType = $penaltyRules['type'] ?? 'per_day';
        $penaltyPercentage = $penaltyRules['percentage'] ?? 10.0;
        $maxPenalty = $penaltyRules['max_penalty'] ?? 100.0;
        $gracePeriodMinutes = $penaltyRules['grace_period_minutes'] ?? 0;

        // Apply grace period
        if ($minutesLate <= $gracePeriodMinutes) {
            return 0.0;
        }

        $effectiveMinutesLate = $minutesLate - $gracePeriodMinutes;
        $penalty = 0.0;

        switch ($penaltyType) {
            case 'per_hour':
                $hoursLate = ceil($effectiveMinutesLate / 60);
                $penalty = $hoursLate * $penaltyPercentage;
                break;

            case 'per_day':
                $daysLate = ceil($effectiveMinutesLate / (24 * 60));
                $penalty = $daysLate * $penaltyPercentage;
                break;

            case 'fixed':
                $penalty = $penaltyPercentage;
                break;

            case 'none':
            default:
                $penalty = 0.0;
                break;
        }

        return min($penalty, $maxPenalty);
    }

    /**
     * Apply late penalty to the score.
     *
     * @param float $penaltyPercentage
     * @param string $reason
     * @return void
     */
    public function applyLatePenalty(float $penaltyPercentage, string $reason = ''): void
    {
        $this->late_penalty_applied = $penaltyPercentage;
        $this->is_late = true;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'late_penalty_applied',
            'penalty_percentage' => $penaltyPercentage,
            'reason' => $reason,
            'applied_at' => now()->toISOString(),
            'applied_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Request late excuse for this submission.
     *
     * @param string $excuse
     * @return void
     */
    public function requestLateExcuse(string $excuse): void
    {
        $this->late_excuse = $excuse;
        $this->late_excuse_approved = false;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'late_excuse_requested',
            'excuse' => $excuse,
            'requested_at' => now()->toISOString(),
            'requested_by' => $this->student_code,
        ];
        $this->score_history = $history;

        $this->save();
    }

    /**
     * Approve or deny late excuse.
     *
     * @param bool $approved
     * @param string $reviewerNotes
     * @return void
     */
    public function processLateExcuse(bool $approved, string $reviewerNotes = ''): void
    {
        $this->late_excuse_approved = $approved;

        // If approved, remove late penalty
        if ($approved) {
            $this->late_penalty_applied = 0.0;
            $this->is_late = false;
        }

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => $approved ? 'late_excuse_approved' : 'late_excuse_denied',
            'reviewer_notes' => $reviewerNotes,
            'processed_at' => now()->toISOString(),
            'processed_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Check if late excuse can be requested.
     *
     * @return bool
     */
    public function canRequestLateExcuse(): bool
    {
        return $this->is_late &&
            empty($this->late_excuse) &&
            !$this->late_excuse_approved;
    }

    /**
     * Check if late excuse is pending approval.
     *
     * @return bool
     */
    public function hasLateExcusePending(): bool
    {
        return !empty($this->late_excuse) && !$this->late_excuse_approved;
    }

    /**
     * Get late submission status information.
     *
     * @return array
     */
    public function getLateSubmissionStatus(): array
    {
        return [
            'is_late' => $this->is_late,
            'minutes_late' => $this->minutes_late,
            'penalty_applied' => $this->late_penalty_applied,
            'has_excuse' => !empty($this->late_excuse),
            'excuse_approved' => $this->late_excuse_approved,
            'excuse_text' => $this->late_excuse,
            'can_request_excuse' => $this->canRequestLateExcuse(),
            'excuse_pending' => $this->hasLateExcusePending(),
        ];
    }

    /**
     * Flag submission for plagiarism concerns.
     *
     * @param float|null $plagiarismScore
     * @param string $notes
     * @return void
     */
    public function flagForPlagiarism(?float $plagiarismScore = null, string $notes = ''): void
    {
        $this->plagiarism_suspected = true;
        $this->plagiarism_score = $plagiarismScore;
        $this->plagiarism_notes = $notes;
        $this->integrity_status = 'under_review';

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'plagiarism_flagged',
            'plagiarism_score' => $plagiarismScore,
            'notes' => $notes,
            'flagged_at' => now()->toISOString(),
            'flagged_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Clear plagiarism flag.
     *
     * @param string $reason
     * @return void
     */
    public function clearPlagiarismFlag(string $reason = ''): void
    {
        $this->plagiarism_suspected = false;
        $this->plagiarism_score = null;
        $this->plagiarism_notes = null;
        $this->integrity_status = 'clean';

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'plagiarism_flag_cleared',
            'reason' => $reason,
            'cleared_at' => now()->toISOString(),
            'cleared_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Update integrity status.
     *
     * @param string $status
     * @param string $notes
     * @return void
     */
    public function updateIntegrityStatus(string $status, string $notes = ''): void
    {
        $validStatuses = [
            'clean',
            'under_review',
            'violation_confirmed',
            'violation_dismissed',
            'pending_hearing',
            'hearing_completed',
            'sanctions_applied'
        ];

        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid integrity status: ' . $status);
        }

        $previousStatus = $this->integrity_status;
        $this->integrity_status = $status;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'integrity_status_updated',
            'previous_status' => $previousStatus,
            'new_status' => $status,
            'notes' => $notes,
            'updated_at' => now()->toISOString(),
            'updated_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Get academic integrity status information.
     *
     * @return array
     */
    public function getAcademicIntegrityStatus(): array
    {
        return [
            'plagiarism_suspected' => $this->plagiarism_suspected,
            'plagiarism_score' => $this->plagiarism_score,
            'plagiarism_notes' => $this->plagiarism_notes,
            'integrity_status' => $this->integrity_status,
            'has_integrity_concerns' => $this->plagiarism_suspected ||
                in_array($this->integrity_status, ['under_review', 'violation_confirmed', 'pending_hearing']),
            'is_under_review' => $this->integrity_status === 'under_review',
            'violation_confirmed' => $this->integrity_status === 'violation_confirmed',
            'can_appeal' => in_array($this->integrity_status, ['violation_confirmed']) && !$this->appeal_requested,
        ];
    }

    /**
     * Check if submission has academic integrity concerns.
     *
     * @return bool
     */
    public function hasIntegrityConcerns(): bool
    {
        return $this->plagiarism_suspected ||
            in_array($this->integrity_status, ['under_review', 'violation_confirmed', 'pending_hearing']);
    }

    /**
     * Check if integrity violation is confirmed.
     *
     * @return bool
     */
    public function hasConfirmedViolation(): bool
    {
        return $this->integrity_status === 'violation_confirmed';
    }

    /**
     * Check if submission is under integrity review.
     *
     * @return bool
     */
    public function isUnderIntegrityReview(): bool
    {
        return $this->integrity_status === 'under_review';
    }

    /**
     * Request an appeal for this submission.
     *
     * @param string $reason
     * @return void
     * @throws \Exception
     */
    public function requestAppeal(string $reason): void
    {
        if ($this->appeal_requested) {
            throw new \Exception('Appeal has already been requested for this submission');
        }

        $eligibleStatuses = ['violation_confirmed', 'sanctions_applied'];
        if (!in_array($this->integrity_status, $eligibleStatuses)) {
            throw new \Exception('Appeals can only be requested for confirmed violations or applied sanctions');
        }

        if (empty(trim($reason))) {
            throw new \Exception('Appeal reason is required');
        }

        $this->appeal_requested = true;
        $this->appeal_requested_at = now();
        $this->appeal_reason = $reason;
        $this->appeal_status = 'pending';

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'appeal_requested',
            'appeal_reason' => $reason,
            'requested_at' => now()->toISOString(),
            'requested_by' => $this->student_code,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Process appeal decision.
     *
     * @param string $decision 'approved' or 'denied'
     * @param string $reviewerNotes
     * @param string $instructorFeedback
     * @return void
     * @throws \Exception
     */
    public function processAppeal(string $decision, string $reviewerNotes = '', string $instructorFeedback = ''): void
    {
        if (!$this->appeal_requested) {
            throw new \Exception('No appeal request found for this submission');
        }

        if ($this->appeal_status !== 'pending') {
            throw new \Exception('Appeal has already been processed');
        }

        $validDecisions = ['approved', 'denied'];
        if (!in_array($decision, $validDecisions)) {
            throw new \Exception('Invalid appeal decision. Must be "approved" or "denied"');
        }

        $previousIntegrityStatus = $this->integrity_status;

        $this->appeal_status = $decision;
        $this->instructor_feedback = $instructorFeedback;
        $this->private_notes = $reviewerNotes;

        // If appeal is approved, update integrity status
        if ($decision === 'approved') {
            $this->integrity_status = 'violation_dismissed';
            $this->plagiarism_suspected = false;
            $this->plagiarism_score = null;
        }

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'appeal_' . $decision,
            'decision' => $decision,
            'reviewer_notes' => $reviewerNotes,
            'instructor_feedback' => $instructorFeedback,
            'previous_integrity_status' => $previousIntegrityStatus,
            'new_integrity_status' => $this->integrity_status,
            'processed_at' => now()->toISOString(),
            'processed_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Check if appeal can be requested.
     *
     * @return bool
     */
    public function canRequestAppeal(): bool
    {
        return !$this->appeal_requested &&
            in_array($this->integrity_status, ['violation_confirmed', 'sanctions_applied']);
    }

    /**
     * Check if appeal is pending.
     *
     * @return bool
     */
    public function hasAppealPending(): bool
    {
        return $this->appeal_requested && $this->appeal_status === 'pending';
    }

    /**
     * Check if appeal was approved.
     *
     * @return bool
     */
    public function isAppealApproved(): bool
    {
        return $this->appeal_requested && $this->appeal_status === 'approved';
    }

    /**
     * Check if appeal was denied.
     *
     * @return bool
     */
    public function isAppealDenied(): bool
    {
        return $this->appeal_requested && $this->appeal_status === 'denied';
    }

    /**
     * Get appeal status information.
     *
     * @return array
     */
    public function getAppealStatus(): array
    {
        return [
            'appeal_requested' => $this->appeal_requested,
            'appeal_reason' => $this->appeal_reason,
            'appeal_status' => $this->appeal_status,
            'appeal_requested_at' => $this->appeal_requested_at?->toISOString(),
            'can_request_appeal' => $this->canRequestAppeal(),
            'appeal_pending' => $this->hasAppealPending(),
            'appeal_approved' => $this->isAppealApproved(),
            'appeal_denied' => $this->isAppealDenied(),
            'instructor_feedback' => $this->instructor_feedback,
            'private_notes' => $this->private_notes,
        ];
    }

    /**
     * Update instructor feedback and private notes.
     *
     * @param string $instructorFeedback
     * @param string $privateNotes
     * @return void
     */
    public function updateFeedback(string $instructorFeedback = '', string $privateNotes = ''): void
    {
        $previousFeedback = $this->instructor_feedback;
        $previousNotes = $this->private_notes;

        $this->instructor_feedback = $instructorFeedback;
        $this->private_notes = $privateNotes;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'feedback_updated',
            'previous_feedback' => $previousFeedback,
            'new_feedback' => $instructorFeedback,
            'previous_notes' => $previousNotes,
            'new_notes' => $privateNotes,
            'updated_at' => now()->toISOString(),
            'updated_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Apply bonus points to the score with reasoning.
     *
     * @param float $bonusPoints
     * @param string $reason
     * @return void
     * @throws \Exception
     */
    public function applyBonusPoints(float $bonusPoints, string $reason): void
    {
        if ($bonusPoints < 0) {
            throw new \Exception('Bonus points cannot be negative');
        }

        if (empty(trim($reason))) {
            throw new \Exception('Bonus reason is required');
        }

        $previousBonus = $this->bonus_points ?? 0;
        $this->bonus_points = $bonusPoints;
        $this->bonus_reason = $reason;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'bonus_points_applied',
            'previous_bonus' => $previousBonus,
            'new_bonus' => $bonusPoints,
            'reason' => $reason,
            'applied_at' => now()->toISOString(),
            'applied_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Remove bonus points from the score.
     *
     * @param string $reason
     * @return void
     */
    public function removeBonusPoints(string $reason = 'Bonus points removed'): void
    {
        $previousBonus = $this->bonus_points ?? 0;
        $previousReason = $this->bonus_reason;

        $this->bonus_points = 0;
        $this->bonus_reason = null;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'bonus_points_removed',
            'previous_bonus' => $previousBonus,
            'previous_reason' => $previousReason,
            'removal_reason' => $reason,
            'removed_at' => now()->toISOString(),
            'removed_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Exclude score from calculations with audit trail.
     *
     * @param string $reason
     * @return void
     * @throws \Exception
     */
    public function excludeScore(string $reason): void
    {
        if (empty(trim($reason))) {
            throw new \Exception('Exclusion reason is required');
        }

        if ($this->score_excluded) {
            throw new \Exception('Score is already excluded');
        }

        $this->score_excluded = true;
        $this->exclusion_reason = $reason;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'score_excluded',
            'reason' => $reason,
            'excluded_at' => now()->toISOString(),
            'excluded_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Include score back in calculations.
     *
     * @param string $reason
     * @return void
     * @throws \Exception
     */
    public function includeScore(string $reason): void
    {
        if (empty(trim($reason))) {
            throw new \Exception('Inclusion reason is required');
        }

        if (!$this->score_excluded) {
            throw new \Exception('Score is not currently excluded');
        }

        $previousReason = $this->exclusion_reason;
        $this->score_excluded = false;
        $this->exclusion_reason = null;

        // Update score history
        $history = $this->score_history ?? [];
        $history[] = [
            'action' => 'score_included',
            'previous_exclusion_reason' => $previousReason,
            'inclusion_reason' => $reason,
            'included_at' => now()->toISOString(),
            'included_by' => auth()->user()?->id,
        ];
        $this->score_history = $history;

        // Update last modified fields
        $this->last_modified_at = now();
        $this->last_modified_by_lecture_id = auth()->user()?->id;

        $this->save();
    }

    /**
     * Calculate weighted score considering all adjustments.
     *
     * @param float $componentWeight The weight of the assessment component (0-100)
     * @return float|null The weighted score or null if excluded
     */
    public function calculateWeightedScore(float $componentWeight): ?float
    {
        // Return null if score is excluded from calculations
        if ($this->score_excluded) {
            return null;
        }

        // Start with base percentage score
        $score = $this->percentage_score ?? 0;

        // Apply late penalty if applicable
        if ($this->is_late && $this->late_penalty_applied > 0 && !$this->late_excuse_approved) {
            $score -= $this->late_penalty_applied;
        }

        // Add bonus points if applicable
        if ($this->bonus_points > 0) {
            $score += $this->bonus_points;
        }

        // Ensure score is within valid range (0-100)
        $score = max(0, min(100, $score));

        // Apply component weight to get weighted score
        return ($score * $componentWeight) / 100;
    }

    /**
     * Get bonus points information.
     *
     * @return array
     */
    public function getBonusPointsInfo(): array
    {
        return [
            'has_bonus' => $this->bonus_points > 0,
            'bonus_points' => $this->bonus_points ?? 0,
            'bonus_reason' => $this->bonus_reason,
            'can_apply_bonus' => !$this->score_excluded,
        ];
    }

    /**
     * Get score exclusion information.
     *
     * @return array
     */
    public function getExclusionInfo(): array
    {
        return [
            'is_excluded' => $this->score_excluded,
            'exclusion_reason' => $this->exclusion_reason,
            'can_exclude' => !$this->score_excluded,
            'can_include' => $this->score_excluded,
        ];
    }

    /**
     * Get comprehensive score adjustment information.
     *
     * @return array
     */
    public function getScoreAdjustments(): array
    {
        return [
            'base_score' => $this->percentage_score ?? 0,
            'late_penalty' => $this->is_late && !$this->late_excuse_approved ? $this->late_penalty_applied : 0,
            'bonus_points' => $this->bonus_points ?? 0,
            'is_excluded' => $this->score_excluded,
            'final_score' => $this->score_excluded ? null : $this->calculateFinalScore(),
            'adjustments' => [
                'late_penalty' => [
                    'applied' => $this->is_late && $this->late_penalty_applied > 0,
                    'amount' => $this->late_penalty_applied ?? 0,
                    'excused' => $this->late_excuse_approved,
                ],
                'bonus_points' => [
                    'applied' => $this->bonus_points > 0,
                    'amount' => $this->bonus_points ?? 0,
                    'reason' => $this->bonus_reason,
                ],
                'exclusion' => [
                    'excluded' => $this->score_excluded,
                    'reason' => $this->exclusion_reason,
                ],
            ],
        ];
    }
}
