<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\User;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentManagementService
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    /**
     * Retrieve the complete assessment structure for a course offering.
     */
    public function getAssessmentStructure(CourseOffering $courseOffering): array
    {
        // Get the syllabus for this course offering
        $syllabus = $courseOffering->syllabusTemplate;

        if (! $syllabus) {
            return [
                'components' => [],
                'total_weight' => 0,
                'is_complete' => false,
                'statistics' => [
                    'total_components' => 0,
                    'total_details' => 0,
                    'graded_submissions' => 0,
                    'pending_submissions' => 0,
                ],
            ];
        }

        // Get assessment components with their details and scores
        $components = AssessmentComponent::where('syllabus_template_id', $syllabus->id)
            ->with([
                'details.scores' => function ($query) use ($courseOffering) {
                    $query->where('course_offering_id', $courseOffering->id);
                },
            ])
            ->orderBy('created_at')
            ->get();

        $structuredComponents = [];
        $totalWeight = 0;
        $totalGradedSubmissions = 0;
        $totalPendingSubmissions = 0;
        $totalDetails = 0;

        foreach ($components as $component) {
            $componentData = [
                'id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'type_name' => $component->type_name,
                'weight' => $component->weight,
                'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                'details' => [],
                'grading_statistics' => $this->calculateComponentGradingStatistics($component, $courseOffering),
                'submission_counts' => $this->calculateComponentSubmissionCounts($component, $courseOffering),
            ];

            // Add details if they exist
            foreach ($component->details as $detail) {
                $totalDetails++;
                $detailData = [
                    'id' => $detail->id,
                    'name' => $detail->name,
                    'weight' => $detail->weight,
                    'grading_statistics' => $this->calculateDetailGradingStatistics($detail, $courseOffering),
                    'submission_counts' => $this->calculateDetailSubmissionCounts($detail, $courseOffering),
                ];

                // Count submissions for this detail
                $gradedCount = $detail->scores->where('score_status', 'final')->count();
                $pendingCount = $detail->scores->whereIn('score_status', ['draft', 'provisional'])->count();

                $totalGradedSubmissions += $gradedCount;
                $totalPendingSubmissions += $pendingCount;

                $componentData['details'][] = $detailData;
            }

            // If no details, count submissions at component level
            if ($component->details->isEmpty()) {
                // For components without details, we would need to implement direct scoring
                // For now, we'll leave this as placeholder
            }

            $totalWeight += $component->weight;
            $structuredComponents[] = $componentData;
        }

        return [
            'components' => $structuredComponents,
            'total_weight' => $totalWeight,
            'is_complete' => $totalWeight == 100.0,
            'statistics' => [
                'total_components' => $components->count(),
                'total_details' => $totalDetails,
                'graded_submissions' => $totalGradedSubmissions,
                'pending_submissions' => $totalPendingSubmissions,
            ],
        ];
    }

    /**
     * Calculate grading statistics for an assessment component.
     */
    public function calculateComponentGradingStatistics(AssessmentComponent $component, CourseOffering $courseOffering): array
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
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($component) {
            $query->where('assessment_component_id', $component->id);
        })
            ->where('course_offering_id', $courseOffering->id)
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
     * Calculate submission counts for an assessment component.
     */
    public function calculateComponentSubmissionCounts(AssessmentComponent $component, CourseOffering $courseOffering): array
    {
        $counts = DB::table('assessment_component_detail_scores as scores')
            ->join('assessment_component_details as details', 'scores.assessment_component_detail_id', '=', 'details.id')
            ->where('details.assessment_component_id', $component->id)
            ->where('scores.course_offering_id', $courseOffering->id)
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
     * Calculate grading statistics for an assessment component detail.
     */
    public function calculateDetailGradingStatistics(AssessmentComponentDetail $detail, CourseOffering $courseOffering): array
    {
        $scores = $detail->scores()
            ->where('course_offering_id', $courseOffering->id)
//            ->where('score_excluded', false)
//            ->where('score_status', 'final')
            ->whereNotNull('points_earned')
            ->get();

        if ($scores->isEmpty()) {
            return [
                'average_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'total_graded' => 0,
            ];
        }

        $percentageScores = $scores->pluck('points_earned');

        return [
            'average_score' => round($percentageScores->average(), 2),
            'highest_score' => $percentageScores->max(),
            'lowest_score' => $percentageScores->min(),
            'total_graded' => $scores->count(),
        ];
    }

    /**
     * Calculate submission counts for an assessment component detail.
     */
    public function calculateDetailSubmissionCounts(AssessmentComponentDetail $detail, CourseOffering $courseOffering): array
    {
        $counts = $detail->scores()
            ->where('course_offering_id', $courseOffering->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "submitted" THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = "graded" THEN 1 ELSE 0 END) as graded,
                SUM(CASE WHEN score_status = "final" THEN 1 ELSE 0 END) as final,
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN plagiarism_suspected = 1 THEN 1 ELSE 0 END) as plagiarism_flagged
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
     * Validate weight constraints for assessment components.
     *
     * @param  array  $components  Array of component data with weights
     * @param  int|null  $excludeComponentId  Component ID to exclude from validation (for updates)
     */
    public function validateWeightConstraints(int $syllabusId, array $components, ?int $excludeComponentId = null): array
    {
        $errors = [];
        $totalWeight = 0;

        // Get existing components (excluding the one being updated if applicable)
        $existingComponents = AssessmentComponent::where('syllabus_id', $syllabusId)
            ->when($excludeComponentId, function ($query, $excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->get();

        // Add existing weights
        $totalWeight += $existingComponents->sum('weight');

        // Add new/updated component weights
        foreach ($components as $component) {
            $weight = $component['weight'] ?? 0;

            // Validate individual component weight
            if ($weight <= 0) {
                $errors[] = 'Component weight must be greater than 0';
            }

            if ($weight > 100) {
                $errors[] = 'Individual component weight cannot exceed 100%';
            }

            $totalWeight += $weight;
        }

        // Check total weight constraint
        if ($totalWeight > 100) {
            $errors[] = "Total assessment weight cannot exceed 100%. Current total: {$totalWeight}%";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_weight' => $totalWeight,
            'remaining_weight' => max(0, 100 - $totalWeight),
        ];
    }

    /**
     * Validate weight constraints for assessment component details within a component.
     *
     * @param  array  $details  Array of detail data with weights
     * @param  int|null  $excludeDetailId  Detail ID to exclude from validation (for updates)
     */
    public function validateDetailWeightConstraints(int $componentId, array $details, ?int $excludeDetailId = null): array
    {
        $errors = [];
        $totalWeight = 0;

        // Get the parent component to check its weight
        $component = AssessmentComponent::find($componentId);
        if (! $component) {
            return [
                'is_valid' => false,
                'errors' => ['Assessment component not found'],
                'total_weight' => 0,
                'remaining_weight' => 0,
            ];
        }

        // Get existing details (excluding the one being updated if applicable)
        $existingDetails = AssessmentComponentDetail::where('assessment_component_id', $componentId)
            ->when($excludeDetailId, function ($query, $excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->get();

        // Add existing weights
        $totalWeight += $existingDetails->sum('weight');

        // Add new/updated detail weights
        foreach ($details as $detail) {
            $weight = $detail['weight'] ?? 0;

            // Validate individual detail weight
            if ($weight <= 0) {
                $errors[] = 'Detail weight must be greater than 0';
            }

            $totalWeight += $weight;
        }

        // Check that total detail weight doesn't exceed component weight
        if ($totalWeight > $component->weight) {
            $errors[] = "Total detail weight ({$totalWeight}%) cannot exceed component weight ({$component->weight}%)";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_weight' => $totalWeight,
            'remaining_weight' => max(0, $component->weight - $totalWeight),
            'component_weight' => $component->weight,
        ];
    }

    /**
     * Create a new assessment component.
     *
     * @throws \Exception
     */
    public function createAssessmentComponent(array $data): AssessmentComponent
    {
        // Validate weight constraints before creating
        $weightValidation = $this->validateWeightConstraints(
            $data['syllabus_id'],
            [['weight' => $data['weight']]]
        );

        if (! $weightValidation['is_valid']) {
            throw new \Exception('Weight validation failed: '.implode(', ', $weightValidation['errors']));
        }

        return DB::transaction(function () use ($data) {
            return AssessmentComponent::create($data);
        });
    }

    /**
     * Update an existing assessment component.
     *
     * @throws \Exception
     */
    public function updateAssessmentComponent(AssessmentComponent $component, array $data): AssessmentComponent
    {
        // If weight is being updated, validate constraints
        if (isset($data['weight'])) {
            $weightValidation = $this->validateWeightConstraints(
                $component->syllabus_id,
                [['weight' => $data['weight']]],
                $component->id
            );

            if (! $weightValidation['is_valid']) {
                throw new \Exception('Weight validation failed: '.implode(', ', $weightValidation['errors']));
            }
        }

        return DB::transaction(function () use ($component, $data) {
            $component->update($data);

            return $component->fresh();
        });
    }

    /**
     * Create a new assessment component detail.
     *
     * @throws \Exception
     */
    public function createAssessmentComponentDetail(array $data): AssessmentComponentDetail
    {
        // Validate weight constraints before creating
        $weightValidation = $this->validateDetailWeightConstraints(
            $data['assessment_component_id'],
            [['weight' => $data['weight']]]
        );

        if (! $weightValidation['is_valid']) {
            throw new \Exception('Detail weight validation failed: '.implode(', ', $weightValidation['errors']));
        }

        return DB::transaction(function () use ($data) {
            return AssessmentComponentDetail::create($data);
        });
    }

    /**
     * Update an existing assessment component detail.
     *
     * @throws \Exception
     */
    public function updateAssessmentComponentDetail(AssessmentComponentDetail $detail, array $data): AssessmentComponentDetail
    {
        // If weight is being updated, validate constraints
        if (isset($data['weight'])) {
            $weightValidation = $this->validateDetailWeightConstraints(
                $detail->assessment_component_id,
                [['weight' => $data['weight']]],
                $detail->id
            );

            if (! $weightValidation['is_valid']) {
                throw new \Exception('Detail weight validation failed: '.implode(', ', $weightValidation['errors']));
            }
        }

        return DB::transaction(function () use ($detail, $data) {
            $detail->update($data);

            return $detail->fresh();
        });
    }

    /**
     * Get grading data for a specific student across all assessments.
     */
    public function getGradingDataByStudent(CourseOffering $courseOffering, int $studentId): array
    {
        $student = $this->studentReferences->find($studentId)
            ?? throw new \RuntimeException("Student reference #{$studentId} cannot be resolved.");

        // Verify student is enrolled
        $isEnrolled = $courseOffering->courseRegistrations()
            ->where('student_id', $student->id)
            ->exists();

        if (! $isEnrolled) {
            throw new \Exception('Student is not enrolled in this course offering');
        }

        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return [
                'student' => $this->formatStudentData($student),
                'assessments' => [],
                'summary' => [
                    'total_assessments' => 0,
                    'completed_assessments' => 0,
                    'pending_assessments' => 0,
                    'overall_percentage' => 0,
                ],
            ];
        }

        // Get assessment components with scores for this student
        $assessmentComponents = $syllabus->assessmentComponents()
            ->with(['details.scores' => function ($query) use ($courseOffering, $student) {
                $query->where('course_offering_id', $courseOffering->id)
                    ->where('student_id', $student->id)
                    ->with('assessmentComponentDetail');
            }])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $formattedAssessments = [];
        $totalAssessments = 0;
        $completedAssessments = 0;
        $weightedScore = 0;
        $totalWeight = 0;

        foreach ($assessmentComponents as $component) {
            $componentData = [
                'id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'type_name' => $component->type_name,
                'weight' => $component->weight,
                'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                'due_date' => $component->due_date?->toISOString(),
                'details' => [],
                'component_average' => 0,
                'component_status' => 'not_started',
            ];

            $detailScores = [];
            $detailWeightedScore = 0;
            $detailTotalWeight = 0;
            $hasAnyScore = false;
            $allScoresComplete = true;

            foreach ($component->details as $detail) {
                $score = $detail->scores->first();
                $totalAssessments++;

                $detailData = [
                    'id' => $detail->id,
                    'name' => $detail->name,
                    'description' => $detail->description,
                    'weight' => $detail->weight,
                    'max_points' => $detail->max_points,
                    'due_date' => $detail->due_date?->toISOString(),
                    'score' => null,
                ];

                if ($score) {
                    $hasAnyScore = true;
                    $detailData['score'] = $this->formatScoreData($score);

                    if ($score->score_status === 'final' && ! $score->score_excluded && $score->percentage_score !== null) {
                        $completedAssessments++;
                        $detailWeightedScore += ($score->percentage_score * $detail->weight);
                        $detailTotalWeight += $detail->weight;
                    } else {
                        $allScoresComplete = false;
                    }
                } else {
                    $allScoresComplete = false;
                }

                $detailScores[] = $detailData;
            }

            // Calculate component average
            if ($detailTotalWeight > 0) {
                $componentData['component_average'] = round($detailWeightedScore / $detailTotalWeight, 2);
                $weightedScore += ($componentData['component_average'] * $component->weight);
                $totalWeight += $component->weight;
            }

            // Determine component status
            if (! $hasAnyScore) {
                $componentData['component_status'] = 'not_started';
            } elseif ($allScoresComplete) {
                $componentData['component_status'] = 'completed';
            } else {
                $componentData['component_status'] = 'in_progress';
            }

            $componentData['details'] = $detailScores;
            $formattedAssessments[] = $componentData;
        }

        $overallPercentage = $totalWeight > 0 ? round($weightedScore / $totalWeight, 2) : 0;

        return [
            'student' => $this->formatStudentData($student),
            'assessments' => $formattedAssessments,
            'summary' => [
                'total_assessments' => $totalAssessments,
                'completed_assessments' => $completedAssessments,
                'pending_assessments' => $totalAssessments - $completedAssessments,
                'overall_percentage' => $overallPercentage,
                'total_weight_covered' => $totalWeight,
            ],
        ];
    }

    /**
     * Get grading data for all students in a specific assessment component.
     */
    public function getGradingDataByComponent(CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): array
    {
        // Verify the assessment component belongs to this course offering
        if ($assessmentComponent->syllabus_template_id !== $courseOffering->syllabusTemplate?->id) {
            throw new \Exception('Assessment component does not belong to this course offering');
        }

        // Get all enrolled students
        $enrolledStudentIds = $courseOffering->courseRegistrations()
            ->get()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
        $enrolledStudents = collect($this->studentReferences->findMany($enrolledStudentIds))
            ->sortBy('fullName');

        // Get assessment component details with all scores
        $assessmentDetails = $assessmentComponent->details()
            ->with(['scores' => function ($query) use ($courseOffering) {
                $query->where('course_offering_id', $courseOffering->id)
                    ->with('student');
            }])
            ->orderBy('created_at')
            ->get();

        $formattedDetails = [];
        $componentStatistics = [
            'total_students' => $enrolledStudents->count(),
            'total_submissions' => 0,
            'graded_submissions' => 0,
            'average_score' => 0,
            'completion_rate' => 0,
        ];

        foreach ($assessmentDetails as $detail) {
            $studentScores = [];
            $detailScores = [];
            $gradedCount = 0;

            foreach ($enrolledStudents as $student) {
                $score = $detail->scores->where('student_id', $student->id)->first();

                $studentData = [
                    'student' => $this->formatStudentData($student),
                    'score' => $score ? $this->formatScoreData($score) : null,
                ];

                if ($score) {
                    $componentStatistics['total_submissions']++;
                    if ($score->score_status === 'final') {
                        $componentStatistics['graded_submissions']++;
                        $gradedCount++;
                        if ($score->percentage_score !== null && ! $score->score_excluded) {
                            $detailScores[] = $score->percentage_score;
                        }
                    }
                }

                $studentScores[] = $studentData;
            }

            $detailStatistics = [
                'total_students' => $enrolledStudents->count(),
                'submitted_count' => $detail->scores->count(),
                'graded_count' => $gradedCount,
                'average_score' => ! empty($detailScores) ? round(array_sum($detailScores) / count($detailScores), 2) : 0,
                'highest_score' => ! empty($detailScores) ? max($detailScores) : 0,
                'lowest_score' => ! empty($detailScores) ? min($detailScores) : 0,
                'completion_rate' => $enrolledStudents->count() > 0 ? round(($gradedCount / $enrolledStudents->count()) * 100, 2) : 0,
            ];

            $formattedDetails[] = [
                'id' => $detail->id,
                'name' => $detail->name,
                'description' => $detail->description,
                'weight' => $detail->weight,
                'max_points' => $detail->max_points,
                'due_date' => $detail->due_date?->toISOString(),
                'student_scores' => $studentScores,
                'statistics' => $detailStatistics,
            ];
        }

        // Calculate overall component statistics
        if ($componentStatistics['graded_submissions'] > 0) {
            $allScores = [];
            foreach ($assessmentDetails as $detail) {
                foreach ($detail->scores as $score) {
                    if ($score->score_status === 'final' && $score->percentage_score !== null && ! $score->score_excluded) {
                        $allScores[] = $score->percentage_score;
                    }
                }
            }

            if (! empty($allScores)) {
                $componentStatistics['average_score'] = round(array_sum($allScores) / count($allScores), 2);
            }
        }

        $componentStatistics['completion_rate'] = $enrolledStudents->count() > 0
            ? round(($componentStatistics['graded_submissions'] / ($enrolledStudents->count() * $assessmentDetails->count())) * 100, 2)
            : 0;

        return [
            'assessment_component' => [
                'id' => $assessmentComponent->id,
                'name' => $assessmentComponent->name,
                'code' => $assessmentComponent->code,
                'type' => $assessmentComponent->type,
                'type_name' => $assessmentComponent->type_name,
                'weight' => $assessmentComponent->weight,
                'due_date' => $assessmentComponent->due_date?->toISOString(),
                'is_required_to_sit_final_exam' => $assessmentComponent->is_required_to_sit_final_exam,
            ],
            'details' => $formattedDetails,
            'statistics' => $componentStatistics,
        ];
    }

    /**
     * Format student data for API responses.
     */
    private function formatStudentData(StudentReference $student): array
    {
        return [
            'id' => $student->id,
            'student_id' => $student->studentCode,
            'full_name' => $student->fullName,
            'email' => $student->email,
        ];
    }

    /**
     * Format score data for API responses.
     */
    public function formatScoreData(AssessmentComponentDetailScore $score): array
    {
        return [
            'id' => $score->id,
            'points_earned' => $score->points_earned,
            'percentage_score' => $score->percentage_score,
            'letter_grade' => $score->letter_grade,
            'status' => $score->status,
            'score_status' => $score->score_status,
            'is_late' => $score->is_late,
            'minutes_late' => $score->minutes_late,
            'late_penalty_applied' => $score->late_penalty_applied,
            'late_excuse_approved' => $score->late_excuse_approved,
            'late_excuse' => $score->late_excuse,
            'bonus_points' => $score->bonus_points,
            'bonus_reason' => $score->bonus_reason,
            'score_excluded' => $score->score_excluded,
            'exclusion_reason' => $score->exclusion_reason,
            'plagiarism_suspected' => $score->plagiarism_suspected,
            'plagiarism_score' => $score->plagiarism_score,
            'plagiarism_notes' => $score->plagiarism_notes,
            'integrity_status' => $score->integrity_status,
            'appeal_requested' => $score->appeal_requested,
            'appeal_reason' => $score->appeal_reason,
            'appeal_status' => $score->appeal_status,
            'instructor_feedback' => $score->instructor_feedback,
            'private_notes' => $score->private_notes,
            'graded_by_lecture_id' => $score->graded_by_lecture_id,
            'graded_at' => $score->graded_at?->toISOString(),
            'last_modified_by_lecture_id' => $score->last_modified_by_lecture_id,
            'last_modified_at' => $score->last_modified_at?->toISOString(),
            'created_at' => $score->created_at->toISOString(),
            'updated_at' => $score->updated_at->toISOString(),
        ];
    }

    /**
     * Calculate late penalty for a submission based on assessment component rules.
     */
    public function calculateLatePenalty(AssessmentComponentDetailScore $score, Carbon $submissionTime): array
    {
        $assessmentComponent = $score->assessmentComponentDetail->assessmentComponent;

        // Get the deadline - try component detail first, then component
        $deadline = $score->assessmentComponentDetail->due_date ?? $assessmentComponent->due_date;

        if (! $deadline) {
            return [
                'is_late' => false,
                'minutes_late' => 0,
                'penalty_percentage' => 0.0,
                'penalty_applied' => false,
                'message' => 'No deadline set for this assessment',
            ];
        }

        // Check if submission is late
        if ($submissionTime->lte($deadline)) {
            return [
                'is_late' => false,
                'minutes_late' => 0,
                'penalty_percentage' => 0.0,
                'penalty_applied' => false,
                'message' => 'Submission is on time',
            ];
        }

        $minutesLate = $submissionTime->diffInMinutes($deadline);

        // Get penalty rules from assessment component
        $penaltyRules = [
            'type' => $assessmentComponent->late_penalty_type ?? 'none',
            'percentage' => $assessmentComponent->late_penalty_percentage ?? 0.0,
            'max_penalty' => 100.0, // Maximum penalty is 100%
            'grace_period_minutes' => 0, // Could be configurable in the future
        ];

        // Calculate penalty using the model method
        $penaltyPercentage = $score->calculateLatePenalty($submissionTime, $deadline, $penaltyRules);

        return [
            'is_late' => true,
            'minutes_late' => $minutesLate,
            'penalty_percentage' => $penaltyPercentage,
            'penalty_applied' => $penaltyPercentage > 0,
            'penalty_type' => $penaltyRules['type'],
            'deadline' => $deadline->toISOString(),
            'submission_time' => $submissionTime->toISOString(),
            'message' => $this->formatLatePenaltyMessage($minutesLate, $penaltyPercentage, $penaltyRules['type']),
        ];
    }

    /**
     * Process late submission and apply penalty if applicable.
     */
    public function processLateSubmission(AssessmentComponentDetailScore $score, Carbon $submissionTime): array
    {
        $penaltyCalculation = $this->calculateLatePenalty($score, $submissionTime);

        if (! $penaltyCalculation['is_late']) {
            // Update score to mark as on time
            $score->update([
                'is_late' => false,
                'minutes_late' => 0,
                'late_penalty_applied' => 0.0,
                'submitted_at' => $submissionTime,
            ]);

            return $penaltyCalculation;
        }

        // Update score with late submission data
        $score->update([
            'is_late' => true,
            'minutes_late' => $penaltyCalculation['minutes_late'],
            'submitted_at' => $submissionTime,
        ]);

        // Apply penalty if applicable
        if ($penaltyCalculation['penalty_applied']) {
            $score->applyLatePenalty(
                $penaltyCalculation['penalty_percentage'],
                "Late submission penalty: {$penaltyCalculation['message']}"
            );
        }

        return $penaltyCalculation;
    }

    /**
     * Process late excuse approval workflow.
     */
    public function processLateExcuseApproval(AssessmentComponentDetailScore $score, bool $approved, string $reviewerNotes = '', ?int $reviewerId = null): array
    {
        if (! $score->hasLateExcusePending()) {
            throw new \Exception('No late excuse pending for this submission');
        }

        // Set the reviewer ID if provided
        if ($reviewerId) {
            auth()->setUser(User::find($reviewerId));
        }

        $score->processLateExcuse($approved, $reviewerNotes);

        $result = [
            'approved' => $approved,
            'reviewer_notes' => $reviewerNotes,
            'processed_at' => now()->toISOString(),
            'processed_by' => $reviewerId,
            'penalty_removed' => false,
            'message' => $approved ? 'Late excuse approved' : 'Late excuse denied',
        ];

        if ($approved) {
            $result['penalty_removed'] = true;
            $result['message'] .= ' - Late penalty has been removed';
        }

        return $result;
    }

    /**
     * Get late submission statistics for a course offering.
     */
    public function getLateSubmissionStatistics(CourseOffering $courseOffering): array
    {
        $stats = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->selectRaw('
                COUNT(*) as total_submissions,
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_submissions,
                SUM(CASE WHEN is_late = 1 AND late_penalty_applied > 0 THEN 1 ELSE 0 END) as penalized_submissions,
                SUM(CASE WHEN late_excuse IS NOT NULL THEN 1 ELSE 0 END) as excuse_requests,
                SUM(CASE WHEN late_excuse_approved = 1 THEN 1 ELSE 0 END) as approved_excuses,
                AVG(CASE WHEN is_late = 1 THEN minutes_late ELSE NULL END) as avg_minutes_late,
                AVG(CASE WHEN is_late = 1 AND late_penalty_applied > 0 THEN late_penalty_applied ELSE NULL END) as avg_penalty_applied
            ')
            ->first();

        return [
            'total_submissions' => $stats->total_submissions ?? 0,
            'late_submissions' => $stats->late_submissions ?? 0,
            'late_submission_rate' => $stats->total_submissions > 0
                ? round(($stats->late_submissions / $stats->total_submissions) * 100, 2)
                : 0,
            'penalized_submissions' => $stats->penalized_submissions ?? 0,
            'excuse_requests' => $stats->excuse_requests ?? 0,
            'approved_excuses' => $stats->approved_excuses ?? 0,
            'excuse_approval_rate' => $stats->excuse_requests > 0
                ? round(($stats->approved_excuses / $stats->excuse_requests) * 100, 2)
                : 0,
            'avg_minutes_late' => $stats->avg_minutes_late ? round($stats->avg_minutes_late, 0) : 0,
            'avg_penalty_applied' => $stats->avg_penalty_applied ? round($stats->avg_penalty_applied, 2) : 0,
        ];
    }

    /**
     * Get students with pending late excuse requests.
     */
    public function getPendingLateExcuses(CourseOffering $courseOffering): Collection
    {
        return AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('late_excuse')
            ->where('late_excuse_approved', false)
            ->with([
                'student',
                'assessmentComponentDetail.assessmentComponent',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Format late penalty message for display.
     */
    private function formatLatePenaltyMessage(int $minutesLate, float $penaltyPercentage, string $penaltyType): string
    {
        if ($penaltyPercentage == 0) {
            return "Submission is {$this->formatTimeDelay($minutesLate)} late, but no penalty applied";
        }

        $timeDelay = $this->formatTimeDelay($minutesLate);

        switch ($penaltyType) {
            case 'per_hour':
                $hoursLate = ceil($minutesLate / 60);

                return "Submission is {$timeDelay} late ({$hoursLate} hour".($hoursLate > 1 ? 's' : '')."), penalty: {$penaltyPercentage}%";

            case 'per_day':
                $daysLate = ceil($minutesLate / (24 * 60));

                return "Submission is {$timeDelay} late ({$daysLate} day".($daysLate > 1 ? 's' : '')."), penalty: {$penaltyPercentage}%";

            case 'fixed':
                return "Submission is {$timeDelay} late, fixed penalty: {$penaltyPercentage}%";

            default:
                return "Submission is {$timeDelay} late, penalty: {$penaltyPercentage}%";
        }
    }

    /**
     * Format time delay in a human-readable format.
     */
    private function formatTimeDelay(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minute".($minutes > 1 ? 's' : '');
        }

        if ($minutes < 1440) { // Less than 24 hours
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;

            $result = "{$hours} hour".($hours > 1 ? 's' : '');
            if ($remainingMinutes > 0) {
                $result .= " and {$remainingMinutes} minute".($remainingMinutes > 1 ? 's' : '');
            }

            return $result;
        }

        $days = floor($minutes / 1440);
        $remainingHours = floor(($minutes % 1440) / 60);

        $result = "{$days} day".($days > 1 ? 's' : '');
        if ($remainingHours > 0) {
            $result .= " and {$remainingHours} hour".($remainingHours > 1 ? 's' : '');
        }

        return $result;
    }

    /**
     * Process academic integrity flag for a submission.
     *
     * @throws \Exception
     */
    public function processAcademicIntegrityFlag(AssessmentComponentDetailScore $score, array $data): array
    {
        // Validate required data
        if (! isset($data['plagiarism_suspected'])) {
            throw new \Exception('plagiarism_suspected flag is required');
        }

        $plagiarismSuspected = (bool) $data['plagiarism_suspected'];
        $plagiarismScore = $data['plagiarism_score'] ?? null;
        $plagiarismNotes = $data['plagiarism_notes'] ?? '';
        $integrityStatus = $data['integrity_status'] ?? null;

        // Validate plagiarism score if provided
        if ($plagiarismScore !== null) {
            if (! is_numeric($plagiarismScore) || $plagiarismScore < 0 || $plagiarismScore > 100) {
                throw new \Exception('Plagiarism score must be between 0 and 100');
            }
            $plagiarismScore = (float) $plagiarismScore;
        }

        // Validate integrity status
        $validIntegrityStatuses = [
            'clean',
            'under_review',
            'violation_confirmed',
            'violation_dismissed',
            'pending_hearing',
            'hearing_completed',
            'sanctions_applied',
        ];

        if ($integrityStatus && ! in_array($integrityStatus, $validIntegrityStatuses)) {
            throw new \Exception('Invalid integrity status. Valid values: '.implode(', ', $validIntegrityStatuses));
        }

        // Set default integrity status based on plagiarism flag
        if (! $integrityStatus) {
            $integrityStatus = $plagiarismSuspected ? 'under_review' : 'clean';
        }

        // Record previous state for history
        $previousState = [
            'plagiarism_suspected' => $score->plagiarism_suspected,
            'plagiarism_score' => $score->plagiarism_score,
            'plagiarism_notes' => $score->plagiarism_notes,
            'integrity_status' => $score->integrity_status,
        ];

        // Update the score record
        $score->update([
            'plagiarism_suspected' => $plagiarismSuspected,
            'plagiarism_score' => $plagiarismScore,
            'plagiarism_notes' => $plagiarismNotes,
            'integrity_status' => $integrityStatus,
            'last_modified_at' => now(),
            'last_modified_by_lecture_id' => auth()->user()?->id,
        ]);

        // Update score history
        $history = $score->score_history ?? [];
        $history[] = [
            'action' => 'academic_integrity_flag_updated',
            'previous_state' => $previousState,
            'new_state' => [
                'plagiarism_suspected' => $plagiarismSuspected,
                'plagiarism_score' => $plagiarismScore,
                'plagiarism_notes' => $plagiarismNotes,
                'integrity_status' => $integrityStatus,
            ],
            'flagged_at' => now()->toISOString(),
            'flagged_by' => auth()->user()?->id,
        ];
        $score->score_history = $history;
        $score->save();

        return [
            'success' => true,
            'plagiarism_suspected' => $plagiarismSuspected,
            'plagiarism_score' => $plagiarismScore,
            'plagiarism_notes' => $plagiarismNotes,
            'integrity_status' => $integrityStatus,
            'flagged_at' => now()->toISOString(),
            'flagged_by' => auth()->user()?->id,
            'message' => $plagiarismSuspected
                ? 'Academic integrity concern flagged successfully'
                : 'Academic integrity flag cleared successfully',
        ];
    }

    /**
     * Get student grades table data with sorting and filtering.
     */
    public function getGradeTableData(
        AssessmentComponentDetail $assessmentDetail,
        CourseOffering $courseOffering,
        array $filters = []
    ): array {
        // Get enrolled students
        $enrolledStudents = $courseOffering->courseRegistrations()
            ->with('student')
            ->get()
            ->pluck('student');

        // Build query for scores
        $query = AssessmentComponentDetailScore::where('assessment_component_detail_id', $assessmentDetail->id)
            ->where('course_offering_id', $courseOffering->id)
            ->with(['student', 'gradedBy', 'lastModifiedBy']);

        // Apply filters
        $this->applyGradeFilters($query, $filters);

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'student_name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        if ($sortBy === 'student_name') {
            $query->join('students', 'assessment_component_detail_scores.student_id', '=', 'students.id')
                ->orderBy('students.full_name', $sortOrder)
                ->select('assessment_component_detail_scores.*');
        } elseif ($sortBy === 'student_id') {
            $query->join('students', 'assessment_component_detail_scores.student_id', '=', 'students.id')
                ->orderBy('students.student_id', $sortOrder)
                ->select('assessment_component_detail_scores.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginate results
        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        $scores = $query->paginate($perPage, ['*'], 'page', $page);

        // Format the response
        $formattedScores = [];
        foreach ($scores->items() as $score) {
            $student = $score->student;
            $formattedScores[] = [
                'score_id' => $score->id,
                'student' => $this->formatStudentData($student),
                'score_data' => $this->formatScoreData($score),
                'submission_info' => [
                    'submitted_at' => $score->submitted_at?->toISOString(),
                    'submission_attempt' => $score->submission_attempt,
                    'is_late' => $score->is_late,
                    'minutes_late' => $score->minutes_late,
                    'late_penalty_applied' => $score->late_penalty_applied,
                ],
                'grading_info' => [
                    'graded_by' => $score->gradedBy ? [
                        'id' => $score->gradedBy->id,
                        'name' => $score->gradedBy->name,
                    ] : null,
                    'graded_at' => $score->graded_at?->toISOString(),
                    'last_modified_by' => $score->lastModifiedBy ? [
                        'id' => $score->lastModifiedBy->id,
                        'name' => $score->lastModifiedBy->name,
                    ] : null,
                    'last_modified_at' => $score->last_modified_at?->toISOString(),
                ],
                'flags' => [
                    'plagiarism_suspected' => $score->plagiarism_suspected,
                    'score_excluded' => $score->score_excluded,
                    'appeal_requested' => $score->appeal_requested,
                    'is_extra_credit' => $score->is_extra_credit,
                    'is_makeup' => $score->is_makeup,
                ],
            ];
        }

        // Add students without scores
        $studentsWithScores = $scores->pluck('student_id')->toArray();
        $studentsWithoutScores = $enrolledStudents->whereNotIn('id', $studentsWithScores);

        foreach ($studentsWithoutScores as $student) {
            $formattedScores[] = [
                'score_id' => null,
                'student' => $this->formatStudentData($student),
                'score_data' => null,
                'submission_info' => null,
                'grading_info' => null,
                'flags' => null,
            ];
        }

        return [
            'data' => $formattedScores,
            'pagination' => [
                'total' => $scores->total() + $studentsWithoutScores->count(),
                'per_page' => $scores->perPage(),
                'current_page' => $scores->currentPage(),
                'last_page' => $scores->lastPage(),
                'from' => $scores->firstItem(),
                'to' => $scores->lastItem(),
            ],
            'assessment_detail' => [
                'id' => $assessmentDetail->id,
                'name' => $assessmentDetail->name,
                'max_points' => $assessmentDetail->max_points,
                'weight' => $assessmentDetail->weight,
                'due_date' => $assessmentDetail->due_date?->toISOString(),
            ],
        ];
    }

    /**
     * Apply filters to grade query.
     */
    private function applyGradeFilters($query, array $filters): void
    {
        // Status filters
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['score_status'])) {
            $query->where('score_status', $filters['score_status']);
        }

        // Score range filters
        if (isset($filters['min_score'])) {
            $query->where('percentage_score', '>=', $filters['min_score']);
        }

        if (isset($filters['max_score'])) {
            $query->where('percentage_score', '<=', $filters['max_score']);
        }

        // Letter grade filter
        if (! empty($filters['letter_grade'])) {
            $query->where('letter_grade', $filters['letter_grade']);
        }

        // Boolean filters
        if (isset($filters['is_late'])) {
            $query->where('is_late', $filters['is_late']);
        }

        if (isset($filters['plagiarism_suspected'])) {
            $query->where('plagiarism_suspected', $filters['plagiarism_suspected']);
        }

        if (isset($filters['score_excluded'])) {
            $query->where('score_excluded', $filters['score_excluded']);
        }

        if (isset($filters['appeal_requested'])) {
            $query->where('appeal_requested', $filters['appeal_requested']);
        }

        // Date filters
        if (! empty($filters['submitted_after'])) {
            $query->where('submitted_at', '>=', $filters['submitted_after']);
        }

        if (! empty($filters['submitted_before'])) {
            $query->where('submitted_at', '<=', $filters['submitted_before']);
        }

        if (! empty($filters['graded_after'])) {
            $query->where('graded_at', '>=', $filters['graded_after']);
        }

        if (! empty($filters['graded_before'])) {
            $query->where('graded_at', '<=', $filters['graded_before']);
        }

        // Group filter
        if (! empty($filters['group_id'])) {
            $query->where('student_group_id', $filters['group_id']);
        }

        // Search filter (student name or ID)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Calculate grade statistics and distribution for an assessment detail.
     */
    public function calculateGradeStatistics(
        AssessmentComponentDetail $assessmentDetail,
        CourseOffering $courseOffering
    ): array {
        $scores = AssessmentComponentDetailScore::where('assessment_component_detail_id', $assessmentDetail->id)
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_excluded', false)
            ->whereNotNull('percentage_score')
            ->get();

        if ($scores->isEmpty()) {
            return $this->getEmptyStatistics();
        }

        $percentageScores = $scores->pluck('percentage_score')->sort()->values();
        $count = $percentageScores->count();

        // Basic statistics
        $mean = $percentageScores->average();
        $median = $this->calculateMedian($percentageScores);
        $mode = $this->calculateMode($percentageScores);
        $standardDeviation = $this->calculateStandardDeviation($percentageScores, $mean);

        // Quartiles
        $quartiles = $this->calculateQuartiles($percentageScores);

        // Grade distribution
        $gradeDistribution = $this->calculateGradeDistribution($scores);

        // Score ranges
        $scoreRanges = $this->calculateScoreRanges($percentageScores);

        // Performance metrics
        $performanceMetrics = $this->calculatePerformanceMetrics($scores, $courseOffering);

        return [
            'summary' => [
                'total_students' => $courseOffering->courseRegistrations()->count(),
                'graded_count' => $count,
                'pending_count' => $courseOffering->courseRegistrations()->count() - $count,
                'mean' => round($mean, 2),
                'median' => round($median, 2),
                'mode' => $mode ? round($mode, 2) : null,
                'standard_deviation' => round($standardDeviation, 2),
                'min_score' => $percentageScores->min(),
                'max_score' => $percentageScores->max(),
                'range' => $percentageScores->max() - $percentageScores->min(),
            ],
            'quartiles' => $quartiles,
            'grade_distribution' => $gradeDistribution,
            'score_ranges' => $scoreRanges,
            'performance_metrics' => $performanceMetrics,
            'visualization_data' => [
                'histogram' => $this->generateHistogramData($percentageScores),
                'box_plot' => $this->generateBoxPlotData($percentageScores, $quartiles),
                'cumulative_frequency' => $this->generateCumulativeFrequency($percentageScores),
            ],
        ];
    }

    /**
     * Calculate median from a collection of values.
     */
    private function calculateMedian($values)
    {
        $count = $values->count();
        if ($count === 0) {
            return 0;
        }

        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }

        return $values[floor($count / 2)];
    }

    /**
     * Calculate mode from a collection of values.
     */
    private function calculateMode($values)
    {
        $frequency = $values->countBy()->sortDesc();
        $maxFrequency = $frequency->first();

        if ($maxFrequency === 1) {
            return null; // No mode if all values appear once
        }

        return (float) $frequency->filter(fn ($count) => $count === $maxFrequency)->keys()->first();
    }

    /**
     * Calculate standard deviation.
     */
    private function calculateStandardDeviation($values, $mean)
    {
        $count = $values->count();
        if ($count <= 1) {
            return 0;
        }

        $variance = $values->map(fn ($value) => pow($value - $mean, 2))->sum() / ($count - 1);

        return sqrt($variance);
    }

    /**
     * Calculate quartiles.
     */
    private function calculateQuartiles($values)
    {
        $count = $values->count();
        if ($count === 0) {
            return ['q1' => 0, 'q2' => 0, 'q3' => 0];
        }

        return [
            'q1' => $this->calculatePercentile($values, 25),
            'q2' => $this->calculatePercentile($values, 50), // Median
            'q3' => $this->calculatePercentile($values, 75),
        ];
    }

    /**
     * Calculate percentile.
     */
    private function calculatePercentile($values, $percentile)
    {
        $count = $values->count();
        $index = ($percentile / 100) * ($count - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($lower === $upper) {
            return $values[$lower];
        }

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    /**
     * Calculate grade distribution.
     */
    private function calculateGradeDistribution($scores)
    {
        $grades = ['A+' => 0, 'A' => 0, 'A-' => 0, 'B+' => 0, 'B' => 0, 'B-' => 0,
            'C+' => 0, 'C' => 0, 'C-' => 0, 'D+' => 0, 'D' => 0, 'F' => 0];

        foreach ($scores as $score) {
            $letterGrade = $score->letter_grade ?? $this->calculateLetterGrade($score->percentage_score);
            if (isset($grades[$letterGrade])) {
                $grades[$letterGrade]++;
            }
        }

        $total = array_sum($grades);
        $distribution = [];

        foreach ($grades as $grade => $count) {
            $distribution[] = [
                'grade' => $grade,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Calculate letter grade from percentage.
     */
    private function calculateLetterGrade($percentage)
    {
        if ($percentage >= 97) {
            return 'A+';
        }
        if ($percentage >= 93) {
            return 'A';
        }
        if ($percentage >= 90) {
            return 'A-';
        }
        if ($percentage >= 87) {
            return 'B+';
        }
        if ($percentage >= 83) {
            return 'B';
        }
        if ($percentage >= 80) {
            return 'B-';
        }
        if ($percentage >= 77) {
            return 'C+';
        }
        if ($percentage >= 73) {
            return 'C';
        }
        if ($percentage >= 70) {
            return 'C-';
        }
        if ($percentage >= 67) {
            return 'D+';
        }
        if ($percentage >= 60) {
            return 'D';
        }

        return 'F';
    }

    /**
     * Calculate score ranges distribution.
     */
    private function calculateScoreRanges($scores)
    {
        $ranges = [
            '90-100' => 0,
            '80-89' => 0,
            '70-79' => 0,
            '60-69' => 0,
            '50-59' => 0,
            '40-49' => 0,
            '30-39' => 0,
            '20-29' => 0,
            '10-19' => 0,
            '0-9' => 0,
        ];

        foreach ($scores as $score) {
            $range = $this->getScoreRange($score);
            if (isset($ranges[$range])) {
                $ranges[$range]++;
            }
        }

        $total = $scores->count();
        $distribution = [];

        foreach ($ranges as $range => $count) {
            $distribution[] = [
                'range' => $range,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        }

        return array_reverse($distribution); // Show highest scores first
    }

    /**
     * Get score range for a given score.
     */
    private function getScoreRange($score)
    {
        $value = floor($score / 10) * 10;
        if ($value === 100) {
            return '90-100';
        }

        return $value.'-'.($value + 9);
    }

    /**
     * Calculate performance metrics.
     */
    private function calculatePerformanceMetrics($scores, CourseOffering $courseOffering)
    {
        $totalEnrolled = $courseOffering->courseRegistrations()->count();
        $submitted = $scores->where('status', '!=', 'not_submitted')->count();
        $graded = $scores->where('score_status', 'final')->count();
        $passed = $scores->where('percentage_score', '>=', 60)->count();
        $failed = $scores->where('percentage_score', '<', 60)->count();
        $late = $scores->where('is_late', true)->count();

        return [
            'submission_rate' => $totalEnrolled > 0 ? round(($submitted / $totalEnrolled) * 100, 2) : 0,
            'completion_rate' => $totalEnrolled > 0 ? round(($graded / $totalEnrolled) * 100, 2) : 0,
            'pass_rate' => $graded > 0 ? round(($passed / $graded) * 100, 2) : 0,
            'fail_rate' => $graded > 0 ? round(($failed / $graded) * 100, 2) : 0,
            'late_submission_rate' => $submitted > 0 ? round(($late / $submitted) * 100, 2) : 0,
            'average_late_penalty' => $scores->where('late_penalty_applied', '>', 0)->avg('late_penalty_applied') ?? 0,
        ];
    }

    /**
     * Generate histogram data for visualization.
     */
    private function generateHistogramData($scores)
    {
        $bins = [];
        for ($i = 0; $i <= 100; $i += 5) {
            $bins[] = [
                'range' => "{$i}-".($i + 4),
                'min' => $i,
                'max' => $i + 4,
                'count' => $scores->filter(fn ($score) => $score >= $i && $score < $i + 5)->count(),
            ];
        }

        return $bins;
    }

    /**
     * Generate box plot data.
     */
    private function generateBoxPlotData($scores, $quartiles)
    {
        $outliers = $this->calculateOutliers($scores, $quartiles);

        return [
            'min' => $scores->min(),
            'q1' => $quartiles['q1'],
            'median' => $quartiles['q2'],
            'q3' => $quartiles['q3'],
            'max' => $scores->max(),
            'outliers' => $outliers,
        ];
    }

    /**
     * Calculate outliers using IQR method.
     */
    private function calculateOutliers($scores, $quartiles)
    {
        $iqr = $quartiles['q3'] - $quartiles['q1'];
        $lowerBound = $quartiles['q1'] - (1.5 * $iqr);
        $upperBound = $quartiles['q3'] + (1.5 * $iqr);

        return $scores->filter(fn ($score) => $score < $lowerBound || $score > $upperBound)->values()->toArray();
    }

    /**
     * Generate cumulative frequency data.
     */
    private function generateCumulativeFrequency($scores)
    {
        $cumulative = [];
        $total = $scores->count();
        $runningCount = 0;

        for ($i = 0; $i <= 100; $i += 5) {
            $runningCount += $scores->filter(fn ($score) => $score >= $i && $score < $i + 5)->count();
            $cumulative[] = [
                'score' => $i + 4,
                'cumulative_count' => $runningCount,
                'cumulative_percentage' => $total > 0 ? round(($runningCount / $total) * 100, 2) : 0,
            ];
        }

        return $cumulative;
    }

    /**
     * Get empty statistics structure.
     */
    private function getEmptyStatistics(): array
    {
        return [
            'summary' => [
                'total_students' => 0,
                'graded_count' => 0,
                'pending_count' => 0,
                'mean' => 0,
                'median' => 0,
                'mode' => null,
                'standard_deviation' => 0,
                'min_score' => 0,
                'max_score' => 0,
                'range' => 0,
            ],
            'quartiles' => ['q1' => 0, 'q2' => 0, 'q3' => 0],
            'grade_distribution' => [],
            'score_ranges' => [],
            'performance_metrics' => [
                'submission_rate' => 0,
                'completion_rate' => 0,
                'pass_rate' => 0,
                'fail_rate' => 0,
                'late_submission_rate' => 0,
                'average_late_penalty' => 0,
            ],
            'visualization_data' => [
                'histogram' => [],
                'box_plot' => [],
                'cumulative_frequency' => [],
            ],
        ];
    }

    /**
     * Bulk create or update grades.
     */
    public function bulkUpsertGrades(
        AssessmentComponentDetail $assessmentDetail,
        CourseOffering $courseOffering,
        array $gradesData,
        int $lecturerId
    ): array {
        $results = [
            'created' => [],
            'updated' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($gradesData as $gradeData) {
                try {
                    $studentId = $gradeData['student_id'] ?? null;

                    if (! $studentId) {
                        $results['errors'][] = [
                            'data' => $gradeData,
                            'error' => 'Student ID is required',
                        ];

                        continue;
                    }

                    // Verify student is enrolled
                    $isEnrolled = $courseOffering->courseRegistrations()
                        ->where('student_id', $studentId)
                        ->exists();

                    if (! $isEnrolled) {
                        $results['errors'][] = [
                            'student_id' => $studentId,
                            'error' => 'Student is not enrolled in this course',
                        ];

                        continue;
                    }

                    // Find or create score record
                    $score = AssessmentComponentDetailScore::firstOrNew([
                        'assessment_component_detail_id' => $assessmentDetail->id,
                        'student_id' => $studentId,
                        'course_offering_id' => $courseOffering->id,
                    ]);

                    $isNew = ! $score->exists;

                    // Update score data
                    $score->fill(array_merge($gradeData, [
                        'graded_by_lecture_id' => $lecturerId,
                        'graded_at' => now(),
                        'last_modified_by_lecture_id' => $lecturerId,
                        'last_modified_at' => now(),
                    ]));

                    // Calculate percentage if points provided
                    if (isset($gradeData['points_earned']) && $assessmentDetail->max_points > 0) {
                        $score->percentage_score = ($gradeData['points_earned'] / $assessmentDetail->max_points) * 100;
                    }

                    // Set letter grade if percentage provided
                    if (isset($score->percentage_score) && ! isset($gradeData['letter_grade'])) {
                        $score->letter_grade = $this->calculateLetterGrade($score->percentage_score);
                    }

                    $score->save();

                    if ($isNew) {
                        $results['created'][] = [
                            'student_id' => $studentId,
                            'score_id' => $score->id,
                        ];
                    } else {
                        $results['updated'][] = [
                            'student_id' => $studentId,
                            'score_id' => $score->id,
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'data' => $gradeData,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Get academic integrity statistics for a course offering.
     */
    public function getAcademicIntegrityStatistics(CourseOffering $courseOffering): array
    {
        $stats = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->selectRaw('
                COUNT(*) as total_submissions,
                SUM(CASE WHEN plagiarism_suspected = 1 THEN 1 ELSE 0 END) as flagged_submissions,
                SUM(CASE WHEN integrity_status = "violation_confirmed" THEN 1 ELSE 0 END) as confirm
                SUM(CASE WHEN integrity_status = "under_review" THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN integrity_status = "pending_hearing" THEN 1 ELSE 0 END) as pending_hearing,
                AVG(CASE WHEN plagiarism_score IS NOT NULL THEN plagiarism_score ELSE NULL END) as avg_plagiarism_score
            ')
            ->first();

        return [
            'total_submissions' => $stats->total_submissions ?? 0,
            'flagged_submissions' => $stats->flagged_submissions ?? 0,
            'flagged_rate' => $stats->total_submissions > 0
                ? round(($stats->flagged_submissions / $stats->total_submissions) * 100, 2)
                : 0,
            'confirmed_violations' => $stats->confirmed_violations ?? 0,
            'under_review' => $stats->under_review ?? 0,
            'pending_hearing' => $stats->pending_hearing ?? 0,
            'avg_plagiarism_score' => $stats->avg_plagiarism_score ? round($stats->avg_plagiarism_score, 2) : 0,
        ];
    }

    /**
     * Get submissions flagged for academic integrity concerns.
     *
     * @param  string|null  $status  Filter by integrity status
     */
    public function getFlaggedSubmissions(CourseOffering $courseOffering, ?string $status = null): Collection
    {
        $query = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->where('plagiarism_suspected', true)
            ->with([
                'student',
                'assessmentComponentDetail.assessmentComponent',
            ]);

        if ($status) {
            $query->where('integrity_status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Update integrity status workflow.
     *
     * @throws \Exception
     */
    public function updateIntegrityStatus(AssessmentComponentDetailScore $score, string $newStatus, string $notes = ''): array
    {
        $validStatuses = [
            'clean',
            'under_review',
            'violation_confirmed',
            'violation_dismissed',
            'pending_hearing',
            'hearing_completed',
            'sanctions_applied',
        ];

        if (! in_array($newStatus, $validStatuses)) {
            throw new \Exception('Invalid integrity status. Valid values: '.implode(', ', $validStatuses));
        }

        $previousStatus = $score->integrity_status;

        // Update the score record
        $score->update([
            'integrity_status' => $newStatus,
            'last_modified_at' => now(),
            'last_modified_by_lecture_id' => auth()->user()?->id,
        ]);

        // Update score history
        $history = $score->score_history ?? [];
        $history[] = [
            'action' => 'integrity_status_updated',
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'updated_at' => now()->toISOString(),
            'updated_by' => auth()->user()?->id,
        ];
        $score->score_history = $history;
        $score->save();

        return [
            'success' => true,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'updated_at' => now()->toISOString(),
            'updated_by' => auth()->user()?->id,
            'message' => "Integrity status updated from '{$previousStatus}' to '{$newStatus}'",
        ];
    }

    /**
     * Process appeal request for a submission.
     *
     * @throws \Exception
     */
    public function processAppealRequest(AssessmentComponentDetailScore $score, string $appealReason): array
    {
        // Validate that appeal can be requested
        if ($score->appeal_requested) {
            throw new \Exception('Appeal has already been requested for this submission');
        }

        // Check if submission is eligible for appeal
        $eligibleStatuses = ['violation_confirmed', 'sanctions_applied'];
        if (! in_array($score->integrity_status, $eligibleStatuses)) {
            throw new \Exception('Appeals can only be requested for confirmed violations or applied sanctions');
        }

        if (empty(trim($appealReason))) {
            throw new \Exception('Appeal reason is required');
        }

        // Update the score record
        $score->update([
            'appeal_requested' => true,
            'appeal_requested_at' => now(),
            'appeal_reason' => $appealReason,
            'appeal_status' => 'pending',
            'last_modified_at' => now(),
            'last_modified_by_lecture_id' => auth()->user()?->id,
        ]);

        // Update score history
        $history = $score->score_history ?? [];
        $history[] = [
            'action' => 'appeal_requested',
            'appeal_reason' => $appealReason,
            'requested_at' => now()->toISOString(),
            'requested_by' => $score->student_id,
        ];
        $score->score_history = $history;
        $score->save();

        return [
            'success' => true,
            'appeal_requested' => true,
            'appeal_reason' => $appealReason,
            'appeal_status' => 'pending',
            'requested_at' => now()->toISOString(),
            'message' => 'Appeal request submitted successfully',
        ];
    }

    /**
     * Process appeal decision.
     *
     * @param  string  $decision  'approved' or 'denied'
     *
     * @throws \Exception
     */
    public function processAppealDecision(AssessmentComponentDetailScore $score, string $decision, string $reviewerNotes = '', string $instructorFeedback = ''): array
    {
        // Validate appeal exists and is pending
        if (! $score->appeal_requested) {
            throw new \Exception('No appeal request found for this submission');
        }

        if ($score->appeal_status !== 'pending') {
            throw new \Exception('Appeal has already been processed');
        }

        // Validate decision
        $validDecisions = ['approved', 'denied'];
        if (! in_array($decision, $validDecisions)) {
            throw new \Exception('Invalid appeal decision. Must be "approved" or "denied"');
        }

        $previousIntegrityStatus = $score->integrity_status;

        // Update appeal status and feedback
        $updateData = [
            'appeal_status' => $decision,
            'instructor_feedback' => $instructorFeedback,
            'private_notes' => $reviewerNotes,
            'last_modified_at' => now(),
            'last_modified_by_lecture_id' => auth()->user()?->id,
        ];

        // If appeal is approved, update integrity status
        if ($decision === 'approved') {
            $updateData['integrity_status'] = 'violation_dismissed';
            $updateData['plagiarism_suspected'] = false;
            $updateData['plagiarism_score'] = null;
        }

        $score->update($updateData);

        // Update score history
        $history = $score->score_history ?? [];
        $history[] = [
            'action' => 'appeal_'.$decision,
            'decision' => $decision,
            'reviewer_notes' => $reviewerNotes,
            'instructor_feedback' => $instructorFeedback,
            'previous_integrity_status' => $previousIntegrityStatus,
            'new_integrity_status' => $score->integrity_status,
            'processed_at' => now()->toISOString(),
            'processed_by' => auth()->user()?->id,
        ];
        $score->score_history = $history;
        $score->save();

        return [
            'success' => true,
            'appeal_decision' => $decision,
            'reviewer_notes' => $reviewerNotes,
            'instructor_feedback' => $instructorFeedback,
            'previous_integrity_status' => $previousIntegrityStatus,
            'new_integrity_status' => $score->integrity_status,
            'processed_at' => now()->toISOString(),
            'processed_by' => auth()->user()?->id,
            'message' => $decision === 'approved'
                ? 'Appeal approved - integrity violation dismissed'
                : 'Appeal denied - original decision stands',
        ];
    }

    /**
     * Get appeal statistics for a course offering.
     */
    public function getAppealStatistics(CourseOffering $courseOffering): array
    {
        $stats = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->selectRaw('
                COUNT(*) as total_submissions,
                SUM(CASE WHEN appeal_requested = 1 THEN 1 ELSE 0 END) as appeal_requests,
                SUM(CASE WHEN appeal_status = "pending" THEN 1 ELSE 0 END) as pending_appeals,
                SUM(CASE WHEN appeal_status = "approved" THEN 1 ELSE 0 END) as approved_appeals,
                SUM(CASE WHEN appeal_status = "denied" THEN 1 ELSE 0 END) as denied_appeals
            ')
            ->first();

        $totalAppeals = ($stats->approved_appeals ?? 0) + ($stats->denied_appeals ?? 0);

        return [
            'total_submissions' => $stats->total_submissions ?? 0,
            'appeal_requests' => $stats->appeal_requests ?? 0,
            'pending_appeals' => $stats->pending_appeals ?? 0,
            'approved_appeals' => $stats->approved_appeals ?? 0,
            'denied_appeals' => $stats->denied_appeals ?? 0,
            'appeal_rate' => $stats->total_submissions > 0
                ? round(($stats->appeal_requests / $stats->total_submissions) * 100, 2)
                : 0,
            'approval_rate' => $totalAppeals > 0
                ? round(($stats->approved_appeals / $totalAppeals) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get pending appeals for a course offering.
     */
    public function getPendingAppeals(CourseOffering $courseOffering): Collection
    {
        return AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->where('appeal_requested', true)
            ->where('appeal_status', 'pending')
            ->with([
                'student',
                'assessmentComponentDetail.assessmentComponent',
            ])
            ->orderBy('appeal_requested_at', 'asc')
            ->get();
    }

    /**
     * Get all appeals for a course offering.
     *
     * @param  string|null  $status  Filter by appeal status
     */
    public function getAppeals(CourseOffering $courseOffering, ?string $status = null): Collection
    {
        $query = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->where('appeal_requested', true)
            ->with([
                'student',
                'assessmentComponentDetail.assessmentComponent',
            ]);

        if ($status) {
            $query->where('appeal_status', $status);
        }

        return $query->orderBy('appeal_requested_at', 'desc')->get();
    }

    /**
     * Update instructor feedback and private notes.
     */
    public function updateInstructorFeedback(AssessmentComponentDetailScore $score, string $instructorFeedback = '', string $privateNotes = ''): array
    {
        $previousFeedback = $score->instructor_feedback;
        $previousNotes = $score->private_notes;

        // Update the score record
        $score->update([
            'instructor_feedback' => $instructorFeedback,
            'private_notes' => $privateNotes,
            'last_modified_at' => now(),
            'last_modified_by_lecture_id' => auth()->user()?->id,
        ]);

        // Update score history
        $history = $score->score_history ?? [];
        $history[] = [
            'action' => 'feedback_updated',
            'previous_feedback' => $previousFeedback,
            'new_feedback' => $instructorFeedback,
            'previous_notes' => $previousNotes,
            'new_notes' => $privateNotes,
            'updated_at' => now()->toISOString(),
            'updated_by' => auth()->user()?->id,
        ];
        $score->score_history = $history;
        $score->save();

        return [
            'success' => true,
            'instructor_feedback' => $instructorFeedback,
            'private_notes' => $privateNotes,
            'updated_at' => now()->toISOString(),
            'updated_by' => auth()->user()?->id,
            'message' => 'Instructor feedback and notes updated successfully',
        ];
    }

    /**
     * Apply bonus points to a score with reasoning.
     *
     * @throws \Exception
     */
    public function applyBonusPoints(AssessmentComponentDetailScore $score, float $bonusPoints, string $reason): array
    {
        if ($bonusPoints < 0) {
            throw new \Exception('Bonus points cannot be negative');
        }

        if (empty(trim($reason))) {
            throw new \Exception('Bonus reason is required');
        }

        if ($score->score_excluded) {
            throw new \Exception('Cannot apply bonus points to excluded scores');
        }

        $previousBonus = $score->bonus_points ?? 0;
        $score->applyBonusPoints($bonusPoints, $reason);

        return [
            'success' => true,
            'previous_bonus' => $previousBonus,
            'new_bonus' => $bonusPoints,
            'reason' => $reason,
            'applied_at' => now()->toISOString(),
            'applied_by' => auth()->user()?->id,
            'message' => "Bonus points ({$bonusPoints}) applied successfully: {$reason}",
        ];
    }

    /**
     * Remove bonus points from a score.
     */
    public function removeBonusPoints(AssessmentComponentDetailScore $score, string $reason = 'Bonus points removed'): array
    {
        $previousBonus = $score->bonus_points ?? 0;
        $previousReason = $score->bonus_reason;

        if ($previousBonus <= 0) {
            throw new \Exception('No bonus points to remove');
        }

        $score->removeBonusPoints($reason);

        return [
            'success' => true,
            'previous_bonus' => $previousBonus,
            'previous_reason' => $previousReason,
            'removal_reason' => $reason,
            'removed_at' => now()->toISOString(),
            'removed_by' => auth()->user()?->id,
            'message' => "Bonus points ({$previousBonus}) removed successfully",
        ];
    }

    /**
     * Exclude a score from calculations with audit trail.
     *
     * @throws \Exception
     */
    public function excludeScore(AssessmentComponentDetailScore $score, string $reason): array
    {
        if (empty(trim($reason))) {
            throw new \Exception('Exclusion reason is required');
        }

        if ($score->score_excluded) {
            throw new \Exception('Score is already excluded');
        }

        $score->excludeScore($reason);

        return [
            'success' => true,
            'excluded' => true,
            'reason' => $reason,
            'excluded_at' => now()->toISOString(),
            'excluded_by' => auth()->user()?->id,
            'message' => "Score excluded from calculations: {$reason}",
        ];
    }

    /**
     * Include a score back in calculations.
     *
     * @throws \Exception
     */
    public function includeScore(AssessmentComponentDetailScore $score, string $reason): array
    {
        if (empty(trim($reason))) {
            throw new \Exception('Inclusion reason is required');
        }

        if (! $score->score_excluded) {
            throw new \Exception('Score is not currently excluded');
        }

        $previousReason = $score->exclusion_reason;
        $score->includeScore($reason);

        return [
            'success' => true,
            'excluded' => false,
            'previous_exclusion_reason' => $previousReason,
            'inclusion_reason' => $reason,
            'included_at' => now()->toISOString(),
            'included_by' => auth()->user()?->id,
            'message' => "Score included back in calculations: {$reason}",
        ];
    }

    /**
     * Calculate weighted scores for all students in a course offering.
     *
     * @param  bool  $includeExcluded  Whether to include excluded scores in the calculation
     */
    public function calculateWeightedScores(CourseOffering $courseOffering, bool $includeExcluded = false): array
    {
        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return [
                'students' => [],
                'component_weights' => [],
                'total_possible_weight' => 0,
            ];
        }

        // Get assessment components with their weights
        $components = $syllabus->assessmentComponents()
            ->with(['details.scores' => function ($query) use ($courseOffering, $includeExcluded) {
                $query->where('course_offering_id', $courseOffering->id)
                    ->where('score_status', 'final')
                    ->when(! $includeExcluded, function ($q) {
                        $q->where('score_excluded', false);
                    });
            }])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        // Get all enrolled students
        $enrolledStudentIds = $courseOffering->courseRegistrations()
            ->get()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
        $enrolledStudents = collect($this->studentReferences->findMany($enrolledStudentIds))
            ->sortBy('fullName');

        $studentScores = [];
        $componentWeights = [];
        $totalPossibleWeight = 0;

        // Build component weights array
        foreach ($components as $component) {
            $componentWeights[$component->id] = [
                'name' => $component->name,
                'weight' => $component->weight,
                'type' => $component->type,
            ];
            $totalPossibleWeight += $component->weight;
        }

        // Calculate scores for each student
        foreach ($enrolledStudents as $student) {
            $studentData = [
                'student' => $this->formatStudentData($student),
                'component_scores' => [],
                'weighted_total' => 0,
                'total_weight_covered' => 0,
                'percentage' => 0,
            ];

            $totalWeightedScore = 0;
            $totalWeightCovered = 0;

            foreach ($components as $component) {
                $componentScore = null;
                $componentWeightedScore = 0;
                $componentWeightUsed = 0;

                // Calculate average score for this component's details
                $detailScores = [];
                $detailWeights = [];

                foreach ($component->details as $detail) {
                    $score = $detail->scores->where('student_id', $student->id)->first();

                    if ($score && ($includeExcluded || ! $score->score_excluded)) {
                        $finalScore = $score->calculateFinalScore();
                        if ($finalScore !== null) {
                            $detailScores[] = $finalScore;
                            $detailWeights[] = $detail->weight;
                        }
                    }
                }

                // Calculate weighted average for component details
                if (! empty($detailScores)) {
                    $totalDetailWeight = array_sum($detailWeights);
                    if ($totalDetailWeight > 0) {
                        $weightedSum = 0;
                        for ($i = 0; $i < count($detailScores); $i++) {
                            $weightedSum += $detailScores[$i] * ($detailWeights[$i] / $totalDetailWeight);
                        }
                        $componentScore = $weightedSum;
                        $componentWeightedScore = ($componentScore * $component->weight) / 100;
                        $componentWeightUsed = $component->weight;
                    }
                }

                $studentData['component_scores'][$component->id] = [
                    'component_name' => $component->name,
                    'raw_score' => $componentScore,
                    'weighted_score' => $componentWeightedScore,
                    'weight_used' => $componentWeightUsed,
                    'detail_count' => count($detailScores),
                    'has_excluded_scores' => $component->details->flatMap->scores
                        ->where('student_id', $student->id)
                        ->where('score_excluded', true)
                        ->isNotEmpty(),
                ];

                $totalWeightedScore += $componentWeightedScore;
                $totalWeightCovered += $componentWeightUsed;
            }

            $studentData['weighted_total'] = round($totalWeightedScore, 2);
            $studentData['total_weight_covered'] = $totalWeightCovered;
            $studentData['percentage'] = $totalWeightCovered > 0
                ? round(($totalWeightedScore / $totalWeightCovered) * 100, 2)
                : 0;

            $studentScores[] = $studentData;
        }

        return [
            'students' => $studentScores,
            'component_weights' => $componentWeights,
            'total_possible_weight' => $totalPossibleWeight,
            'calculation_settings' => [
                'include_excluded' => $includeExcluded,
                'only_final_scores' => true,
            ],
        ];
    }

    /**
     * Get score adjustment statistics for a course offering.
     */
    public function getScoreAdjustmentStatistics(CourseOffering $courseOffering): array
    {
        $stats = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->selectRaw('
                COUNT(*) as total_scores,
                SUM(CASE WHEN bonus_points > 0 THEN 1 ELSE 0 END) as scores_with_bonus,
                SUM(CASE WHEN score_excluded = 1 THEN 1 ELSE 0 END) as excluded_scores,
                SUM(CASE WHEN is_late = 1 AND late_penalty_applied > 0 THEN 1 ELSE 0 END) as penalized_scores,
                AVG(CASE WHEN bonus_points > 0 THEN bonus_points ELSE NULL END) as avg_bonus_points,
                AVG(CASE WHEN is_late = 1 AND late_penalty_applied > 0 THEN late_penalty_applied ELSE NULL END) as avg_penalty,
                SUM(bonus_points) as total_bonus_points_awarded
            ')
            ->first();

        return [
            'total_scores' => $stats->total_scores ?? 0,
            'scores_with_bonus' => $stats->scores_with_bonus ?? 0,
            'bonus_rate' => $stats->total_scores > 0
                ? round(($stats->scores_with_bonus / $stats->total_scores) * 100, 2)
                : 0,
            'excluded_scores' => $stats->excluded_scores ?? 0,
            'exclusion_rate' => $stats->total_scores > 0
                ? round(($stats->excluded_scores / $stats->total_scores) * 100, 2)
                : 0,
            'penalized_scores' => $stats->penalized_scores ?? 0,
            'penalty_rate' => $stats->total_scores > 0
                ? round(($stats->penalized_scores / $stats->total_scores) * 100, 2)
                : 0,
            'avg_bonus_points' => $stats->avg_bonus_points ? round($stats->avg_bonus_points, 2) : 0,
            'avg_penalty' => $stats->avg_penalty ? round($stats->avg_penalty, 2) : 0,
            'total_bonus_points_awarded' => $stats->total_bonus_points_awarded ?? 0,
        ];
    }

    /**
     * Get scores with adjustments for a course offering.
     *
     * @param  string|null  $adjustmentType  Filter by adjustment type: 'bonus', 'excluded', 'penalty'
     */
    public function getScoresWithAdjustments(CourseOffering $courseOffering, ?string $adjustmentType = null): Collection
    {
        $query = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->with([
                'student',
                'assessmentComponentDetail.assessmentComponent',
            ]);

        switch ($adjustmentType) {
            case 'bonus':
                $query->where('bonus_points', '>', 0);
                break;
            case 'excluded':
                $query->where('score_excluded', true);
                break;
            case 'penalty':
                $query->where('is_late', true)
                    ->where('late_penalty_applied', '>', 0);
                break;
            default:
                $query->where(function ($q) {
                    $q->where('bonus_points', '>', 0)
                        ->orWhere('score_excluded', true)
                        ->orWhere(function ($subQ) {
                            $subQ->where('is_late', true)
                                ->where('late_penalty_applied', '>', 0);
                        });
                });
                break;
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Calculate final grades for all students in a course offering with proper adjustments.
     *
     * @param  array  $options  Calculation options
     */
    public function calculateFinalGrades(CourseOffering $courseOffering, array $options = []): array
    {
        $options = array_merge([
            'include_excluded' => false,
            'only_final_scores' => true,
            'apply_adjustments' => true,
            'generate_audit_trail' => true,
        ], $options);

        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return [
                'students' => [],
                'calculation_summary' => [
                    'total_students' => 0,
                    'components_used' => 0,
                    'total_weight' => 0,
                    'calculation_options' => $options,
                ],
            ];
        }

        // Get assessment components with their weights
        $components = $syllabus->assessmentComponents()
            ->with(['details.scores' => function ($query) use ($courseOffering, $options) {
                $query->where('course_offering_id', $courseOffering->id);
                if ($options['only_final_scores']) {
                    $query->where('score_status', 'final');
                }
                if (! $options['include_excluded']) {
                    $query->where('score_excluded', false);
                }
            }])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        // Get all enrolled students
        $enrolledStudentIds = $courseOffering->courseRegistrations()
            ->get()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
        $enrolledStudents = collect($this->studentReferences->findMany($enrolledStudentIds))
            ->sortBy('fullName');

        $studentGrades = [];
        $calculationSummary = [
            'total_students' => $enrolledStudents->count(),
            'components_used' => $components->count(),
            'total_weight' => $components->sum('weight'),
            'calculation_options' => $options,
            'adjustments_applied' => [
                'bonus_points' => 0,
                'late_penalties' => 0,
                'excluded_scores' => 0,
            ],
        ];

        foreach ($enrolledStudents as $student) {
            $gradeData = $this->calculateStudentFinalGrade($student, $components, $courseOffering, $options);

            // Update summary statistics
            $calculationSummary['adjustments_applied']['bonus_points'] += $gradeData['adjustments']['bonus_count'];
            $calculationSummary['adjustments_applied']['late_penalties'] += $gradeData['adjustments']['penalty_count'];
            $calculationSummary['adjustments_applied']['excluded_scores'] += $gradeData['adjustments']['excluded_count'];

            $studentGrades[] = $gradeData;
        }

        return [
            'students' => $studentGrades,
            'calculation_summary' => $calculationSummary,
        ];
    }

    /**
     * Calculate final grade for a single student with proper adjustments.
     *
     * @param  Collection  $components
     */
    private function calculateStudentFinalGrade(StudentReference $student, $components, CourseOffering $courseOffering, array $options): array
    {
        $studentData = [
            'student' => $this->formatStudentData($student),
            'component_grades' => [],
            'final_grade' => [
                'raw_percentage' => 0,
                'adjusted_percentage' => 0,
                'letter_grade' => null,
                'gpa_points' => null,
            ],
            'grade_breakdown' => [
                'total_weighted_score' => 0,
                'total_weight_used' => 0,
                'components_completed' => 0,
                'components_total' => $components->count(),
            ],
            'adjustments' => [
                'bonus_count' => 0,
                'penalty_count' => 0,
                'excluded_count' => 0,
                'total_bonus_points' => 0,
                'total_penalty_points' => 0,
            ],
            'audit_trail' => [],
        ];

        $totalWeightedScore = 0;
        $totalWeightUsed = 0;
        $auditTrail = [];

        foreach ($components as $component) {
            $componentGrade = $this->calculateComponentGrade($student, $component, $options);

            if ($componentGrade['has_score']) {
                $totalWeightedScore += $componentGrade['weighted_score'];
                $totalWeightUsed += $componentGrade['weight_used'];
                $studentData['grade_breakdown']['components_completed']++;

                // Track adjustments
                $studentData['adjustments']['bonus_count'] += $componentGrade['adjustments']['bonus_count'];
                $studentData['adjustments']['penalty_count'] += $componentGrade['adjustments']['penalty_count'];
                $studentData['adjustments']['excluded_count'] += $componentGrade['adjustments']['excluded_count'];
                $studentData['adjustments']['total_bonus_points'] += $componentGrade['adjustments']['total_bonus'];
                $studentData['adjustments']['total_penalty_points'] += $componentGrade['adjustments']['total_penalty'];

                // Add to audit trail
                if ($options['generate_audit_trail'] && ! empty($componentGrade['audit_trail'])) {
                    $auditTrail = array_merge($auditTrail, $componentGrade['audit_trail']);
                }
            }

            $studentData['component_grades'][] = $componentGrade;
        }

        // Calculate final percentages
        $studentData['grade_breakdown']['total_weighted_score'] = round($totalWeightedScore, 2);
        $studentData['grade_breakdown']['total_weight_used'] = $totalWeightUsed;

        if ($totalWeightUsed > 0) {
            $rawPercentage = ($totalWeightedScore / $totalWeightUsed) * 100;
            $studentData['final_grade']['raw_percentage'] = round($rawPercentage, 2);
            $studentData['final_grade']['adjusted_percentage'] = round($rawPercentage, 2);

            // Convert to letter grade (this would use institutional grading scale)
            $studentData['final_grade']['letter_grade'] = $this->convertToLetterGrade($rawPercentage);
            $studentData['final_grade']['gpa_points'] = $this->convertToGpaPoints($rawPercentage);
        }

        if ($options['generate_audit_trail']) {
            $studentData['audit_trail'] = $auditTrail;
        }

        return $studentData;
    }

    /**
     * Calculate grade for a single component for a student.
     */
    private function calculateComponentGrade(StudentReference $student, AssessmentComponent $component, array $options): array
    {
        $componentData = [
            'component_id' => $component->id,
            'component_name' => $component->name,
            'component_weight' => $component->weight,
            'component_type' => $component->type,
            'has_score' => false,
            'raw_score' => null,
            'adjusted_score' => null,
            'weighted_score' => 0,
            'weight_used' => 0,
            'detail_scores' => [],
            'adjustments' => [
                'bonus_count' => 0,
                'penalty_count' => 0,
                'excluded_count' => 0,
                'total_bonus' => 0,
                'total_penalty' => 0,
            ],
            'audit_trail' => [],
        ];

        $detailScores = [];
        $detailWeights = [];
        $auditTrail = [];

        foreach ($component->details as $detail) {
            $score = $detail->scores->where('student_id', $student->id)->first();

            $detailData = [
                'detail_id' => $detail->id,
                'detail_name' => $detail->name,
                'detail_weight' => $detail->weight,
                'has_score' => false,
                'raw_score' => null,
                'final_score' => null,
                'is_excluded' => false,
                'adjustments' => [],
            ];

            if ($score) {
                $detailData['has_score'] = true;
                $detailData['raw_score'] = $score->percentage_score;
                $detailData['is_excluded'] = $score->score_excluded;

                // Track adjustments for audit trail
                $adjustments = [];

                if ($score->score_excluded) {
                    $componentData['adjustments']['excluded_count']++;
                    $adjustments[] = [
                        'type' => 'exclusion',
                        'reason' => $score->exclusion_reason,
                        'applied_at' => $score->last_modified_at?->toISOString(),
                    ];
                } else {
                    // Calculate final score with adjustments
                    $finalScore = $score->calculateFinalScore();
                    $detailData['final_score'] = $finalScore;

                    if ($finalScore !== null) {
                        $detailScores[] = $finalScore;
                        $detailWeights[] = $detail->weight;

                        // Track bonus points
                        if ($score->bonus_points > 0) {
                            $componentData['adjustments']['bonus_count']++;
                            $componentData['adjustments']['total_bonus'] += $score->bonus_points;
                            $adjustments[] = [
                                'type' => 'bonus',
                                'amount' => $score->bonus_points,
                                'reason' => $score->bonus_reason,
                                'applied_at' => $score->last_modified_at?->toISOString(),
                            ];
                        }

                        // Track late penalties
                        if ($score->is_late && $score->late_penalty_applied > 0 && ! $score->late_excuse_approved) {
                            $componentData['adjustments']['penalty_count']++;
                            $componentData['adjustments']['total_penalty'] += $score->late_penalty_applied;
                            $adjustments[] = [
                                'type' => 'late_penalty',
                                'amount' => $score->late_penalty_applied,
                                'minutes_late' => $score->minutes_late,
                                'applied_at' => $score->last_modified_at?->toISOString(),
                            ];
                        }
                    }
                }

                $detailData['adjustments'] = $adjustments;

                // Add to audit trail
                if ($options['generate_audit_trail'] && ! empty($adjustments)) {
                    $auditTrail[] = [
                        'component' => $component->name,
                        'detail' => $detail->name,
                        'student_id' => $student->id,
                        'score_id' => $score->id,
                        'adjustments' => $adjustments,
                    ];
                }
            }

            $componentData['detail_scores'][] = $detailData;
        }

        // Calculate component score if we have valid detail scores
        if (! empty($detailScores)) {
            $totalDetailWeight = array_sum($detailWeights);
            if ($totalDetailWeight > 0) {
                // Calculate weighted average of detail scores
                $weightedSum = 0;
                for ($i = 0; $i < count($detailScores); $i++) {
                    $weightedSum += $detailScores[$i] * ($detailWeights[$i] / $totalDetailWeight);
                }

                $componentData['has_score'] = true;
                $componentData['raw_score'] = round($weightedSum, 2);
                $componentData['adjusted_score'] = round($weightedSum, 2);
                $componentData['weighted_score'] = ($weightedSum * $component->weight) / 100;
                $componentData['weight_used'] = $component->weight;
            }
        }

        $componentData['audit_trail'] = $auditTrail;

        return $componentData;
    }

    /**
     * Convert percentage score to letter grade.
     */
    private function convertToLetterGrade(float $percentage): ?string
    {
        // This would typically use institutional grading scale
        // For now, using a standard scale
        if ($percentage >= 90) {
            return 'A';
        }
        if ($percentage >= 80) {
            return 'B';
        }
        if ($percentage >= 70) {
            return 'C';
        }
        if ($percentage >= 60) {
            return 'D';
        }

        return 'F';
    }

    /**
     * Convert percentage score to GPA points.
     */
    private function convertToGpaPoints(float $percentage): ?float
    {
        // This would typically use institutional GPA scale
        // For now, using a standard 4.0 scale
        if ($percentage >= 90) {
            return 4.0;
        }
        if ($percentage >= 80) {
            return 3.0;
        }
        if ($percentage >= 70) {
            return 2.0;
        }
        if ($percentage >= 60) {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * Generate comprehensive audit trail for grade calculations.
     */
    public function generateGradeCalculationAuditTrail(CourseOffering $courseOffering, array $options = []): array
    {
        $options['generate_audit_trail'] = true;
        $gradeData = $this->calculateFinalGrades($courseOffering, $options);

        $auditSummary = [
            'calculation_timestamp' => now()->toISOString(),
            'course_offering_id' => $courseOffering->id,
            'calculation_options' => $options,
            'total_students_processed' => count($gradeData['students']),
            'total_adjustments' => $gradeData['calculation_summary']['adjustments_applied'],
            'detailed_audit_trail' => [],
        ];

        // Collect all audit trail entries
        foreach ($gradeData['students'] as $studentGrade) {
            if (! empty($studentGrade['audit_trail'])) {
                $auditSummary['detailed_audit_trail'][] = [
                    'student' => $studentGrade['student'],
                    'adjustments' => $studentGrade['audit_trail'],
                ];
            }
        }

        return $auditSummary;
    }

    /**
     * Update score with proper audit trail for adjustments.
     */
    public function updateScoreWithAuditTrail(AssessmentComponentDetailScore $score, array $data): AssessmentComponentDetailScore
    {
        $originalData = [
            'percentage_score' => $score->percentage_score,
            'bonus_points' => $score->bonus_points,
            'bonus_reason' => $score->bonus_reason,
            'score_excluded' => $score->score_excluded,
            'exclusion_reason' => $score->exclusion_reason,
            'late_penalty_applied' => $score->late_penalty_applied,
        ];

        // Update the score
        $score->update($data);

        // Generate audit trail entry
        $changes = [];
        foreach ($data as $field => $newValue) {
            if (isset($originalData[$field]) && $originalData[$field] != $newValue) {
                $changes[] = [
                    'field' => $field,
                    'old_value' => $originalData[$field],
                    'new_value' => $newValue,
                ];
            }
        }

        if (! empty($changes)) {
            $history = $score->score_history ?? [];
            $history[] = [
                'action' => 'score_updated_with_adjustments',
                'changes' => $changes,
                'updated_at' => now()->toISOString(),
                'updated_by' => auth()->user()?->id,
                'calculation_context' => 'grade_calculation_update',
            ];
            $score->score_history = $history;
            $score->save();
        }

        return $score->fresh();
    }
}
