<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'syllabus_template_id',
        'name',
        'weight',
        'type',
        'is_required_to_sit_final_exam',
        'description',
        'code',
        'due_date',
        'available_from',
        'late_submission_deadline',
        'late_penalty_percentage',
        'late_penalty_type',
        'submission_type',
        'allowed_file_types',
        'max_file_size_mb',
        'max_submissions',
        'allow_resubmission',
        'is_group_work',
        'min_group_size',
        'max_group_size',
        'students_form_groups',
        'assessment_criteria',
        'grading_instructions',
        'is_published',
        'scores_published',
        'is_extra_credit',
        'status',
        'sort_order',
        'category',
        'is_canvas_synced',
        'canvas_assignment_group_id',
        'canvas_group_weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_required_to_sit_final_exam' => 'boolean',
        'due_date' => 'datetime',
        'available_from' => 'datetime',
        'late_submission_deadline' => 'datetime',
        'late_penalty_percentage' => 'decimal:2',
        'allowed_file_types' => 'array',
        'is_group_work' => 'boolean',
        'students_form_groups' => 'boolean',
        'assessment_criteria' => 'array',
        'is_published' => 'boolean',
        'scores_published' => 'boolean',
        'is_extra_credit' => 'boolean',
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
        'attendance' => 'Attendance',
        'other' => 'Other',
    ];

    /**
     * Get the syllabus that owns this assessment component.
     */
    public function syllabusTemplate(): BelongsTo
    {
        return $this->belongsTo(SyllabusTemplate::class, 'syllabus_template_id');
    }

    /**
     * Get all details for this assessment component.
     */
    public function details(): HasMany
    {
        return $this->hasMany(AssessmentComponentDetail::class, 'assessment_component_id');
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

    /**
     * Get grading statistics for this assessment component.
     */
    public function getGradingStatistics(int $courseOfferingId): array
    {
        $statistics = [
            'average_score' => 0,
            'highest_score' => 0,
            'lowest_score' => 0,
            'total_submissions' => 0,
            'graded_submissions' => 0,
            'pending_submissions' => 0,
        ];

        // Get all scores for this component's details
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) {
            $query->where('assessment_component_id', $this->id);
        })
            ->where('course_offering_id', $courseOfferingId)
            ->where('score_excluded', false)
            ->get();

        if ($scores->isEmpty()) {
            return $statistics;
        }

        $finalScores = $scores->where('score_status', 'final');
        $gradedScores = $finalScores->whereNotNull('percentage_score');

        $statistics['total_submissions'] = $scores->count();
        $statistics['graded_submissions'] = $finalScores->count();
        $statistics['pending_submissions'] = $scores->whereIn('score_status', ['draft', 'provisional'])->count();

        if ($gradedScores->isNotEmpty()) {
            $percentageScores = $gradedScores->pluck('percentage_score');
            $statistics['average_score'] = round($percentageScores->average(), 2);
            $statistics['highest_score'] = $percentageScores->max();
            $statistics['lowest_score'] = $percentageScores->min();
        }

        return $statistics;
    }

    /**
     * Get submission counts for this assessment component.
     */
    public function getSubmissionCounts(int $courseOfferingId): array
    {
        $counts = \DB::table('assessment_component_detail_scores as scores')
            ->join('assessment_component_details as details', 'scores.assessment_component_detail_id', '=', 'details.id')
            ->where('details.assessment_component_id', $this->id)
            ->where('scores.course_offering_id', $courseOfferingId)
            ->where('scores.deleted_at', null)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN scores.status = "submitted" THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN scores.status = "graded" THEN 1 ELSE 0 END) as graded,
                SUM(CASE WHEN scores.score_status = "final" THEN 1 ELSE 0 END) as final,
                SUM(CASE WHEN scores.is_late = 1 THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN scores.plagiarism_suspected = 1 THEN 1 ELSE 0 END) as plagiarism_flagged
            ')
            ->first();

        return [
            'total' => $counts->total ?? 0,
            'submitted' => $counts->submitted ?? 0,
            'graded' => $counts->graded ?? 0,
            'final' => $counts->final ?? 0,
            'late' => $counts->late ?? 0,
            'plagiarism_flagged' => $counts->plagiarism_flagged ?? 0,
        ];
    }

    /**
     * Validate total weight constraint for this component's syllabus.
     *
     * @param  float  $newWeight  The new weight for this component
     */
    public function validateTotalWeight(float $newWeight): array
    {
        $errors = [];

        // Get all other components in the same syllabus
        $otherComponents = self::where('syllabus_template_id', $this->syllabus_template_id)
            ->where('id', '!=', $this->id)
            ->get();

        $totalWeight = $otherComponents->sum('weight') + $newWeight;

        // Validate individual weight
        if ($newWeight <= 0) {
            $errors[] = 'Component weight must be greater than 0';
        }

        if ($newWeight > 100) {
            $errors[] = 'Individual component weight cannot exceed 100%';
        }

        // Validate total weight
        if ($totalWeight > 100) {
            $errors[] = "Total assessment weight cannot exceed 100%. Current total would be: {$totalWeight}%";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_weight' => $totalWeight,
            'remaining_weight' => max(0, 100 - $totalWeight),
        ];
    }

    /**
     * Get all student scores for this component.
     */
    public function getStudentScores(Student $student): \Illuminate\Database\Eloquent\Collection
    {
        return AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) {
            $query->where('assessment_component_id', $this->id);
        })
            ->where('student_id', $student->id)
            ->with('assessmentComponentDetail')
            ->get();
    }

    /**
     * Create a default assessment component detail for this component.
     * This is called automatically when a component is created without details.
     */
    public function createDefaultDetail(): AssessmentComponentDetail
    {
        return AssessmentComponentDetail::create([
            'assessment_component_id' => $this->id,
            'name' => $this->name,
            'weight' => 100.00,
        ]);
    }

    /**
     * Ensure this component has at least one detail.
     * If it doesn't have any details, create a default one.
     */
    public function ensureHasDetails(): void
    {
        if ($this->details()->count() === 0) {
            $this->createDefaultDetail();
        }
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for assessment components (critical academic structure)
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get comprehensive fields for logging
     */
    protected function getComprehensiveLogFields(): array
    {
        return [
            'syllabus_template_id',
            'name',
            'weight',
            'type',
            'is_required_to_sit_final_exam',
            'description',
            'code',
            'due_date',
            'is_group_work',
            'is_published',
            'scores_published',
            'is_extra_credit',
            'status',
            'category',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $unit = $this->syllabusTemplate?->unit;
        $unitCode = $unit?->code ?? 'N/A';
        $version = $this->syllabusTemplate?->version;

        return "{$this->name} ({$this->type}) - {$unitCode}" . ($version ? " v{$version}" : '');
    }

    /**
     * Custom activity descriptions for assessment component events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created assessment component: {$identifier}",
            'updated' => "Updated assessment component: {$identifier}",
            'deleted' => "Removed assessment component: {$identifier}",
            'restored' => "Restored assessment component: {$identifier}",
            default => "{$eventName} assessment component: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'component_name' => $this->name,
            'component_type' => $this->type,
            'weight_percentage' => $this->weight,
            'unit_code' => $this->syllabusTemplate?->unit?->code,
            'unit_name' => $this->syllabusTemplate?->unit?->name,
            'syllabus_template_version' => $this->syllabusTemplate?->version,
            'total_detail_weight' => $this->getTotalDetailWeightAttribute(),
            'has_details' => $this->hasDetails(),
            'affects_final_exam' => $this->is_required_to_sit_final_exam,
            'is_group_assessment' => $this->is_group_work,
            'publication_status' => [
                'is_published' => $this->is_published,
                'scores_published' => $this->scores_published,
            ],
        ];

        // Track weight changes (critical for grading)
        if ($this->isDirty('weight') && $this->exists) {
            $properties['weight_change'] = [
                'from' => $this->getOriginal('weight'),
                'to' => $this->weight,
                'validation' => $this->validateTotalWeight($this->weight),
                'impact' => 'affects_grade_calculation',
            ];
        }

        // Track due date changes
        if ($this->isDirty('due_date') && $this->exists) {
            $properties['due_date_change'] = [
                'from' => $this->getOriginal('due_date'),
                'to' => $this->due_date,
                'notification_required' => true,
            ];
        }

        // Track publication status changes
        if ($this->isDirty('is_published') && $this->exists) {
            $properties['publication_change'] = [
                'from' => $this->getOriginal('is_published') ? 'published' : 'unpublished',
                'to' => $this->is_published ? 'published' : 'unpublished',
                'timestamp' => now()->toDateTimeString(),
            ];
        }

        // Track score publication
        if ($this->isDirty('scores_published') && $this->exists) {
            $properties['score_publication'] = [
                'published' => $this->scores_published,
                'timestamp' => now()->toDateTimeString(),
                'affects_students' => true,
            ];
        }

        return $properties;
    }
}
