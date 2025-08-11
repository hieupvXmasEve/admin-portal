<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentReportService
{
    /**
     * Generate overview statistics for assessment reporting.
     */
    public function generateOverviewStatistics(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyOverviewStatistics();
        }

        // Get enrolled students count
        $totalEnrolledStudents = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->count();

        // Get assessment components
        $assessmentComponents = AssessmentComponent::where('syllabus_id', $syllabus->id)
            ->with(['details'])
            ->get();

        $totalComponents = $assessmentComponents->count();
        $totalDetails = $assessmentComponents->sum(function ($component) {
            return $component->details->count();
        });

        // Calculate score statistics
        $scoreStatistics = $this->calculateOverallScoreStatistics($courseOffering, $assessmentComponents);

        // Calculate completion statistics
        $completionStatistics = $this->calculateCompletionStatistics($courseOffering, $assessmentComponents);

        // Calculate submission status distribution
        $submissionStatistics = $this->calculateSubmissionStatistics($courseOffering, $assessmentComponents);

        return [
            'enrollment_statistics' => [
                'total_enrolled_students' => $totalEnrolledStudents,
                'active_students' => $totalEnrolledStudents, // Assuming all enrolled are active
            ],
            'assessment_structure' => [
                'total_components' => $totalComponents,
                'total_details' => $totalDetails,
                'total_weight' => $assessmentComponents->sum('weight'),
                'weight_complete' => $assessmentComponents->sum('weight') == 100.0,
            ],
            'score_statistics' => $scoreStatistics,
            'completion_statistics' => $completionStatistics,
            'submission_statistics' => $submissionStatistics,
            'component_breakdown' => $this->getComponentBreakdown($courseOffering, $assessmentComponents),
        ];
    }

    /**
     * Calculate score distribution for performance analytics.
     */
    public function calculateScoreDistribution(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyScoreDistribution();
        }

        // Get all final scores for this course offering
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail.assessmentComponent', function ($query) use ($syllabus) {
            $query->where('syllabus_id', $syllabus->id);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_status', 'final')
            ->where('score_excluded', false)
            ->whereNotNull('percentage_score')
            ->get();

        if ($scores->isEmpty()) {
            return $this->getEmptyScoreDistribution();
        }

        $percentageScores = $scores->pluck('percentage_score');

        // Calculate distribution by grade ranges
        $gradeRanges = [
            'A' => ['min' => 90, 'max' => 100, 'count' => 0],
            'B' => ['min' => 80, 'max' => 89.99, 'count' => 0],
            'C' => ['min' => 70, 'max' => 79.99, 'count' => 0],
            'D' => ['min' => 60, 'max' => 69.99, 'count' => 0],
            'F' => ['min' => 0, 'max' => 59.99, 'count' => 0],
        ];

        foreach ($percentageScores as $score) {
            foreach ($gradeRanges as $grade => &$range) {
                if ($score >= $range['min'] && $score <= $range['max']) {
                    $range['count']++;
                    break;
                }
            }
        }

        // Calculate histogram data (10-point intervals)
        $histogram = [];
        for ($i = 0; $i < 100; $i += 10) {
            $rangeLabel = $i . '-' . ($i + 9);
            $count = $percentageScores->filter(function ($score) use ($i) {
                return $score >= $i && $score < ($i + 10);
            })->count();

            $histogram[] = [
                'range' => $rangeLabel,
                'min' => $i,
                'max' => $i + 9,
                'count' => $count,
                'percentage' => $scores->count() > 0 ? round(($count / $scores->count()) * 100, 2) : 0,
            ];
        }

        // Handle 100% scores separately
        $perfectScores = $percentageScores->filter(function ($score) {
            return $score == 100;
        })->count();

        if ($perfectScores > 0) {
            $histogram[9]['count'] += $perfectScores;
            $histogram[9]['percentage'] = $scores->count() > 0 ? round(($histogram[9]['count'] / $scores->count()) * 100, 2) : 0;
        }

        return [
            'total_scores' => $scores->count(),
            'average_score' => round($percentageScores->average(), 2),
            'median_score' => $this->calculateMedian($percentageScores->toArray()),
            'highest_score' => $percentageScores->max(),
            'lowest_score' => $percentageScores->min(),
            'standard_deviation' => $this->calculateStandardDeviation($percentageScores->toArray()),
            'grade_distribution' => $gradeRanges,
            'histogram' => $histogram,
            'quartiles' => $this->calculateQuartiles($percentageScores->toArray()),
        ];
    }

    /**
     * Get completion statistics for tracking submission status.
     */
    public function getCompletionStatistics(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyCompletionStatistics();
        }

        $totalEnrolledStudents = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->count();

        $assessmentComponents = AssessmentComponent::where('syllabus_id', $syllabus->id)
            ->with(['details'])
            ->get();

        $totalAssessmentDetails = $assessmentComponents->sum(function ($component) {
            return $component->details->count();
        });

        $expectedSubmissions = $totalEnrolledStudents * $totalAssessmentDetails;

        // Get submission statistics
        $submissionStats = DB::table('assessment_component_detail_scores as scores')
            ->join('assessment_component_details as details', 'scores.assessment_component_detail_id', '=', 'details.id')
            ->join('assessment_components as components', 'details.assessment_component_id', '=', 'components.id')
            ->where('components.syllabus_id', $syllabus->id)
            ->where('scores.course_offering_id', $courseOffering->id)
            ->whereNull('scores.deleted_at')
            ->selectRaw('
                COUNT(*) as total_submissions,
                SUM(CASE WHEN scores.status IN ("submitted", "graded") THEN 1 ELSE 0 END) as submitted_count,
                SUM(CASE WHEN scores.score_status = "final" THEN 1 ELSE 0 END) as graded_count,
                SUM(CASE WHEN scores.score_status = "draft" THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN scores.score_status = "provisional" THEN 1 ELSE 0 END) as provisional_count,
                SUM(CASE WHEN scores.is_late = 1 THEN 1 ELSE 0 END) as late_submissions,
                SUM(CASE WHEN scores.score_excluded = 1 THEN 1 ELSE 0 END) as excluded_submissions
            ')
            ->first();

        $missingSubmissions = $expectedSubmissions - ($submissionStats->total_submissions ?? 0);

        // Calculate completion rates by component
        $componentCompletionRates = [];
        foreach ($assessmentComponents as $component) {
            $componentStats = $this->calculateComponentCompletionRate($courseOffering, $component, $totalEnrolledStudents);
            $componentCompletionRates[] = [
                'component_id' => $component->id,
                'component_name' => $component->name,
                'component_type' => $component->type,
                'weight' => $component->weight,
                'completion_rate' => $componentStats['completion_rate'],
                'submitted_count' => $componentStats['submitted_count'],
                'graded_count' => $componentStats['graded_count'],
                'missing_count' => $componentStats['missing_count'],
            ];
        }

        return [
            'overview' => [
                'total_enrolled_students' => $totalEnrolledStudents,
                'total_assessment_details' => $totalAssessmentDetails,
                'expected_submissions' => $expectedSubmissions,
                'actual_submissions' => $submissionStats->total_submissions ?? 0,
                'missing_submissions' => $missingSubmissions,
                'overall_completion_rate' => $expectedSubmissions > 0 ? round((($submissionStats->graded_count ?? 0) / $expectedSubmissions) * 100, 2) : 0,
            ],
            'submission_status' => [
                'submitted' => $submissionStats->submitted_count ?? 0,
                'graded' => $submissionStats->graded_count ?? 0,
                'draft' => $submissionStats->draft_count ?? 0,
                'provisional' => $submissionStats->provisional_count ?? 0,
                'late' => $submissionStats->late_submissions ?? 0,
                'excluded' => $submissionStats->excluded_submissions ?? 0,
                'missing' => $missingSubmissions,
            ],
            'component_completion_rates' => $componentCompletionRates,
        ];
    }

    /**
     * Calculate overall score statistics.
     */
    private function calculateOverallScoreStatistics(CourseOffering $courseOffering, Collection $assessmentComponents): array
    {
        if ($assessmentComponents->isEmpty()) {
            return [
                'average_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'total_graded_submissions' => 0,
            ];
        }

        $componentIds = $assessmentComponents->pluck('id');

        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_status', 'final')
            ->where('score_excluded', false)
            ->whereNotNull('percentage_score')
            ->get();

        if ($scores->isEmpty()) {
            return [
                'average_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'total_graded_submissions' => 0,
            ];
        }

        $percentageScores = $scores->pluck('percentage_score');

        return [
            'average_score' => round($percentageScores->average(), 2),
            'highest_score' => $percentageScores->max(),
            'lowest_score' => $percentageScores->min(),
            'total_graded_submissions' => $scores->count(),
        ];
    }

    /**
     * Calculate completion statistics.
     */
    private function calculateCompletionStatistics(CourseOffering $courseOffering, Collection $assessmentComponents): array
    {
        if ($assessmentComponents->isEmpty()) {
            return [
                'completion_rate' => 0,
                'completed_assessments' => 0,
                'pending_assessments' => 0,
                'total_assessments' => 0,
            ];
        }

        $totalEnrolledStudents = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->count();

        $totalAssessmentDetails = $assessmentComponents->sum(function ($component) {
            return $component->details->count();
        });

        $expectedSubmissions = $totalEnrolledStudents * $totalAssessmentDetails;

        $componentIds = $assessmentComponents->pluck('id');

        $completedSubmissions = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_status', 'final')
            ->count();

        $pendingSubmissions = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('score_status', ['draft', 'provisional'])
            ->count();

        return [
            'completion_rate' => $expectedSubmissions > 0 ? round(($completedSubmissions / $expectedSubmissions) * 100, 2) : 0,
            'completed_assessments' => $completedSubmissions,
            'pending_assessments' => $pendingSubmissions,
            'total_assessments' => $expectedSubmissions,
        ];
    }

    /**
     * Calculate submission statistics.
     */
    private function calculateSubmissionStatistics(CourseOffering $courseOffering, Collection $assessmentComponents): array
    {
        if ($assessmentComponents->isEmpty()) {
            return [
                'submitted' => 0,
                'graded' => 0,
                'late' => 0,
                'plagiarism_flagged' => 0,
                'appeals_requested' => 0,
                'excluded' => 0,
            ];
        }

        $componentIds = $assessmentComponents->pluck('id');

        $stats = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->selectRaw('
            SUM(CASE WHEN status IN ("submitted", "graded") THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN score_status = "final" THEN 1 ELSE 0 END) as graded,
            SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN plagiarism_suspected = 1 THEN 1 ELSE 0 END) as plagiarism_flagged,
            SUM(CASE WHEN appeal_requested = 1 THEN 1 ELSE 0 END) as appeals_requested,
            SUM(CASE WHEN score_excluded = 1 THEN 1 ELSE 0 END) as excluded
        ')
            ->first();

        return [
            'submitted' => $stats->submitted ?? 0,
            'graded' => $stats->graded ?? 0,
            'late' => $stats->late ?? 0,
            'plagiarism_flagged' => $stats->plagiarism_flagged ?? 0,
            'appeals_requested' => $stats->appeals_requested ?? 0,
            'excluded' => $stats->excluded ?? 0,
        ];
    }

    /**
     * Get component breakdown statistics.
     */
    private function getComponentBreakdown(CourseOffering $courseOffering, Collection $assessmentComponents): array
    {
        $breakdown = [];

        foreach ($assessmentComponents as $component) {
            $componentStats = $component->getGradingStatistics($courseOffering->id);
            $submissionCounts = $component->getSubmissionCounts($courseOffering->id);

            $breakdown[] = [
                'id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'type_name' => $component->type_name,
                'weight' => $component->weight,
                'statistics' => $componentStats,
                'submission_counts' => $submissionCounts,
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate completion rate for a specific component.
     */
    private function calculateComponentCompletionRate(CourseOffering $courseOffering, AssessmentComponent $component, int $totalEnrolledStudents): array
    {
        $detailCount = $component->details->count();
        $expectedSubmissions = $totalEnrolledStudents * $detailCount;

        $stats = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($component) {
            $query->where('assessment_component_id', $component->id);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->selectRaw('
            SUM(CASE WHEN status IN ("submitted", "graded") THEN 1 ELSE 0 END) as submitted_count,
            SUM(CASE WHEN score_status = "final" THEN 1 ELSE 0 END) as graded_count
        ')
            ->first();

        $submittedCount = $stats->submitted_count ?? 0;
        $gradedCount = $stats->graded_count ?? 0;
        $missingCount = $expectedSubmissions - $submittedCount;

        return [
            'submitted_count' => $submittedCount,
            'graded_count' => $gradedCount,
            'missing_count' => $missingCount,
            'completion_rate' => $expectedSubmissions > 0 ? round(($gradedCount / $expectedSubmissions) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate median value from array of numbers.
     */
    private function calculateMedian(array $numbers): float
    {
        if (empty($numbers)) {
            return 0;
        }

        sort($numbers);
        $count = count($numbers);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
        }

        return $numbers[$middle];
    }

    /**
     * Calculate standard deviation from array of numbers.
     */
    private function calculateStandardDeviation(array $numbers): float
    {
        if (empty($numbers)) {
            return 0;
        }

        $mean = array_sum($numbers) / count($numbers);
        $squaredDifferences = array_map(function ($number) use ($mean) {
            return pow($number - $mean, 2);
        }, $numbers);

        $variance = array_sum($squaredDifferences) / count($numbers);

        return round(sqrt($variance), 2);
    }

    /**
     * Calculate quartiles from array of numbers.
     */
    private function calculateQuartiles(array $numbers): array
    {
        if (empty($numbers)) {
            return ['q1' => 0, 'q2' => 0, 'q3' => 0];
        }

        sort($numbers);
        $count = count($numbers);

        $q1Index = floor($count * 0.25);
        $q2Index = floor($count * 0.5);
        $q3Index = floor($count * 0.75);

        return [
            'q1' => $numbers[$q1Index] ?? 0,
            'q2' => $numbers[$q2Index] ?? 0,
            'q3' => $numbers[$q3Index] ?? 0,
        ];
    }

    /**
     * Get empty overview statistics structure.
     */
    private function getEmptyOverviewStatistics(): array
    {
        return [
            'enrollment_statistics' => [
                'total_enrolled_students' => 0,
                'active_students' => 0,
            ],
            'assessment_structure' => [
                'total_components' => 0,
                'total_details' => 0,
                'total_weight' => 0,
                'weight_complete' => false,
            ],
            'score_statistics' => [
                'average_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'total_graded_submissions' => 0,
            ],
            'completion_statistics' => [
                'completion_rate' => 0,
                'completed_assessments' => 0,
                'pending_assessments' => 0,
                'total_assessments' => 0,
            ],
            'submission_statistics' => [
                'submitted' => 0,
                'graded' => 0,
                'late' => 0,
                'plagiarism_flagged' => 0,
                'appeals_requested' => 0,
                'excluded' => 0,
            ],
            'component_breakdown' => [],
        ];
    }

    /**
     * Get empty score distribution structure.
     */
    private function getEmptyScoreDistribution(): array
    {
        return [
            'total_scores' => 0,
            'average_score' => 0,
            'median_score' => 0,
            'highest_score' => 0,
            'lowest_score' => 0,
            'standard_deviation' => 0,
            'grade_distribution' => [
                'A' => ['min' => 90, 'max' => 100, 'count' => 0],
                'B' => ['min' => 80, 'max' => 89.99, 'count' => 0],
                'C' => ['min' => 70, 'max' => 79.99, 'count' => 0],
                'D' => ['min' => 60, 'max' => 69.99, 'count' => 0],
                'F' => ['min' => 0, 'max' => 59.99, 'count' => 0],
            ],
            'histogram' => [],
            'quartiles' => ['q1' => 0, 'q2' => 0, 'q3' => 0],
        ];
    }

    /**
     * Calculate comprehensive performance metrics for a student.
     */
    private function calculateStudentPerformanceMetrics(CourseOffering $courseOffering, Student $student, Collection $assessmentComponents): array
    {
        $componentIds = $assessmentComponents->pluck('id');

        // Get all scores for this student
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('student_id', $student->id)
            ->with(['assessmentComponentDetail.assessmentComponent'])
            ->get();

        $totalWeightedScore = 0;
        $totalWeight = 0;
        $componentScores = [];
        $missingSubmissions = 0;
        $lateSubmissions = 0;
        $integrityConcerns = 0;
        $appealRequests = 0;
        $failingComponents = 0;

        foreach ($assessmentComponents as $component) {
            $componentScores[$component->id] = [];
            $componentTotalScore = 0;
            $componentTotalWeight = 0;
            $componentHasScores = false;

            foreach ($component->details as $detail) {
                $score = $scores->where('assessment_component_detail_id', $detail->id)->first();

                if ($score && $score->score_status === 'final' && ! $score->score_excluded) {
                    $componentScores[$component->id][] = $score->percentage_score;
                    $componentTotalScore += $score->percentage_score * ($detail->weight / 100);
                    $componentTotalWeight += $detail->weight / 100;
                    $componentHasScores = true;

                    if ($score->is_late) {
                        $lateSubmissions++;
                    }
                    if ($score->plagiarism_suspected) {
                        $integrityConcerns++;
                    }
                    if ($score->appeal_requested) {
                        $appealRequests++;
                    }
                } else {
                    $missingSubmissions++;
                }
            }

            if ($componentHasScores && $componentTotalWeight > 0) {
                $componentAverage = $componentTotalScore / $componentTotalWeight;
                $totalWeightedScore += $componentAverage * ($component->weight / 100);
                $totalWeight += $component->weight / 100;

                if ($componentAverage < 60) {
                    $failingComponents++;
                }
            } else {
                $failingComponents++; // No scores means failing
            }
        }

        $weightedAverage = $totalWeight > 0 ? $totalWeightedScore / $totalWeight : 0;
        $lowestComponentScore = $this->calculateLowestComponentScore($componentScores);

        return [
            'weighted_average' => round($weightedAverage, 2),
            'lowest_component_score' => $lowestComponentScore,
            'missing_submissions' => $missingSubmissions,
            'late_submissions' => $lateSubmissions,
            'integrity_concerns' => $integrityConcerns,
            'appeal_requests' => $appealRequests,
            'failing_components' => $failingComponents,
            'total_components' => $assessmentComponents->count(),
            'component_scores' => $componentScores,
        ];
    }

    /**
     * Calculate performance trend for a student.
     */
    private function calculatePerformanceTrend(CourseOffering $courseOffering, Student $student, Collection $assessmentComponents): array
    {
        $componentIds = $assessmentComponents->pluck('id');

        // Get scores ordered by graded date
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('student_id', $student->id)
            ->where('score_status', 'final')
            ->where('score_excluded', false)
            ->whereNotNull('percentage_score')
            ->whereNotNull('graded_at')
            ->orderBy('graded_at')
            ->get();

        if ($scores->count() < 2) {
            return [
                'trend' => 'insufficient_data',
                'improvement_rate' => 0,
                'decline_rate' => 0,
                'recent_scores' => [],
                'trend_analysis' => 'Not enough graded assessments for trend analysis',
            ];
        }

        $recentScores = $scores->pluck('percentage_score')->toArray();
        $firstHalf = array_slice($recentScores, 0, ceil(count($recentScores) / 2));
        $secondHalf = array_slice($recentScores, floor(count($recentScores) / 2));

        $firstHalfAverage = array_sum($firstHalf) / count($firstHalf);
        $secondHalfAverage = array_sum($secondHalf) / count($secondHalf);

        $changeRate = $secondHalfAverage - $firstHalfAverage;
        $changePercentage = abs($changeRate);

        if ($changeRate > 5) {
            $trend = 'improving';
            $improvementRate = $changePercentage;
            $declineRate = 0;
        } elseif ($changeRate < -5) {
            $trend = 'declining';
            $improvementRate = 0;
            $declineRate = $changePercentage;
        } else {
            $trend = 'stable';
            $improvementRate = 0;
            $declineRate = 0;
        }

        return [
            'trend' => $trend,
            'improvement_rate' => round($improvementRate, 2),
            'decline_rate' => round($declineRate, 2),
            'recent_scores' => $recentScores,
            'first_half_average' => round($firstHalfAverage, 2),
            'second_half_average' => round($secondHalfAverage, 2),
            'trend_analysis' => $this->generateTrendAnalysis($trend, $changePercentage),
        ];
    }

    /**
     * Determine risk level based on risk score.
     */
    private function determineRiskLevel(int $riskScore): string
    {
        if ($riskScore >= 80) {
            return 'critical';
        } elseif ($riskScore >= 60) {
            return 'high';
        } elseif ($riskScore >= 40) {
            return 'medium';
        } elseif ($riskScore >= 20) {
            return 'low';
        }

        return 'minimal';
    }

    /**
     * Calculate risk distribution among at-risk students.
     */
    private function calculateRiskDistribution(array $atRiskStudents): array
    {
        $distribution = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'minimal' => 0,
        ];

        foreach ($atRiskStudents as $student) {
            $distribution[$student['risk_level']]++;
        }

        return $distribution;
    }

    /**
     * Generate intervention recommendations based on risk factors.
     */
    private function generateInterventionRecommendations(array $riskFactors, array $performanceMetrics): array
    {
        $recommendations = [];

        foreach ($riskFactors as $factor) {
            switch ($factor['type']) {
                case 'low_average_score':
                    $recommendations[] = [
                        'type' => 'academic_support',
                        'priority' => 'high',
                        'action' => 'Schedule one-on-one tutoring session',
                        'description' => 'Student needs additional academic support to improve understanding of course material',
                    ];
                    break;

                case 'excessive_missing_submissions':
                    $recommendations[] = [
                        'type' => 'engagement_intervention',
                        'priority' => 'high',
                        'action' => 'Contact student to discuss barriers to submission',
                        'description' => 'Identify and address obstacles preventing timely submission of assignments',
                    ];
                    break;

                case 'excessive_late_submissions':
                    $recommendations[] = [
                        'type' => 'time_management',
                        'priority' => 'medium',
                        'action' => 'Refer to academic success center for time management workshop',
                        'description' => 'Help student develop better time management and organizational skills',
                    ];
                    break;

                case 'multiple_failing_components':
                    $recommendations[] = [
                        'type' => 'comprehensive_review',
                        'priority' => 'critical',
                        'action' => 'Arrange meeting to discuss course withdrawal or incomplete options',
                        'description' => 'Student may need to consider alternative academic pathways',
                    ];
                    break;

                case 'academic_integrity_concerns':
                    $recommendations[] = [
                        'type' => 'integrity_counseling',
                        'priority' => 'critical',
                        'action' => 'Refer to academic integrity office for counseling',
                        'description' => 'Address academic integrity violations and provide education on proper academic practices',
                    ];
                    break;

                case 'declining_performance':
                    $recommendations[] = [
                        'type' => 'early_intervention',
                        'priority' => 'medium',
                        'action' => 'Schedule check-in meeting to identify causes of decline',
                        'description' => 'Proactively address factors contributing to declining performance',
                    ];
                    break;
            }
        }

        return $recommendations;
    }

    /**
     * Generate recognition suggestions for exceptional students.
     */
    private function generateRecognitionSuggestions(array $excellenceFactors, array $performanceMetrics): array
    {
        $suggestions = [];

        foreach ($excellenceFactors as $factor) {
            switch ($factor['type']) {
                case 'high_average_score':
                    $suggestions[] = [
                        'type' => 'academic_recognition',
                        'action' => 'Nominate for academic excellence award',
                        'description' => 'Student demonstrates exceptional academic performance',
                    ];
                    break;

                case 'consistent_performance':
                    $suggestions[] = [
                        'type' => 'reliability_recognition',
                        'action' => 'Highlight as model student in class',
                        'description' => 'Student shows consistent high-quality work across all assessments',
                    ];
                    break;

                case 'improving_performance':
                    $suggestions[] = [
                        'type' => 'improvement_recognition',
                        'action' => 'Acknowledge improvement in class or via email',
                        'description' => 'Student shows significant improvement and dedication',
                    ];
                    break;

                case 'punctual_submissions':
                    $suggestions[] = [
                        'type' => 'responsibility_recognition',
                        'action' => 'Consider for teaching assistant or peer mentor role',
                        'description' => 'Student demonstrates excellent time management and responsibility',
                    ];
                    break;
            }
        }

        return $suggestions;
    }

    /**
     * Calculate integrity risk level for a student.
     */
    private function calculateIntegrityRiskLevel(array $studentData): string
    {
        $riskScore = 0;

        $riskScore += $studentData['plagiarism_cases'] * 30;
        $riskScore += $studentData['appeal_requests'] * 10;
        $riskScore += ($studentData['highest_plagiarism_score'] / 100) * 40;

        if ($riskScore >= 70) {
            return 'critical';
        } elseif ($riskScore >= 50) {
            return 'high';
        } elseif ($riskScore >= 30) {
            return 'medium';
        } elseif ($riskScore >= 10) {
            return 'low';
        }

        return 'minimal';
    }

    /**
     * Generate integrity monitoring recommendations.
     */
    private function generateIntegrityRecommendations(array $overallStats, array $studentIssues): array
    {
        $recommendations = [];

        if ($overallStats['total_flagged_submissions'] > 0) {
            $flaggedRate = ($overallStats['total_flagged_submissions'] / max($overallStats['total_flagged_submissions'] + $overallStats['resolved_cases'], 1)) * 100;

            if ($flaggedRate > 10) {
                $recommendations[] = [
                    'type' => 'course_wide_intervention',
                    'priority' => 'high',
                    'action' => 'Review assessment design and academic integrity policies',
                    'description' => 'High rate of integrity concerns suggests need for preventive measures',
                ];
            }
        }

        if ($overallStats['pending_investigations'] > 5) {
            $recommendations[] = [
                'type' => 'administrative_support',
                'priority' => 'medium',
                'action' => 'Request additional support for integrity investigations',
                'description' => 'Large number of pending cases may require additional resources',
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate lowest component score for a student.
     */
    private function calculateLowestComponentScore(array $componentScores): float
    {
        $componentAverages = [];

        foreach ($componentScores as $scores) {
            if (! empty($scores)) {
                $componentAverages[] = array_sum($scores) / count($scores);
            }
        }

        return ! empty($componentAverages) ? round(min($componentAverages), 2) : 0;
    }

    /**
     * Generate trend analysis description.
     */
    private function generateTrendAnalysis(string $trend, float $changePercentage): string
    {
        switch ($trend) {
            case 'improving':
                return "Student performance is improving with an average increase of {$changePercentage}% in recent assessments.";
            case 'declining':
                return "Student performance is declining with an average decrease of {$changePercentage}% in recent assessments.";
            case 'stable':
                return 'Student performance remains stable with minimal variation in recent assessments.';
            default:
                return 'Insufficient data available for trend analysis.';
        }
    }

    /**
     * Get empty at-risk students structure.
     */
    private function getEmptyAtRiskStudents(): array
    {
        return [
            'total_students' => 0,
            'at_risk_count' => 0,
            'risk_distribution' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'minimal' => 0,
            ],
            'students' => [],
            'criteria_used' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get empty exceptional students structure.
     */
    private function getEmptyExceptionalStudents(): array
    {
        return [
            'total_students' => 0,
            'exceptional_count' => 0,
            'students' => [],
            'criteria_used' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get empty integrity monitoring structure.
     */
    private function getEmptyIntegrityMonitoring(): array
    {
        return [
            'overall_statistics' => [
                'total_flagged_submissions' => 0,
                'unique_students_flagged' => 0,
                'pending_investigations' => 0,
                'resolved_cases' => 0,
                'appeal_requests' => 0,
                'average_plagiarism_score' => 0,
            ],
            'student_integrity_issues' => [],
            'component_integrity_statistics' => [],
            'recommendations' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate comprehensive grade matrix for all students and assessments.
     */
    public function generateGradeMatrix(CourseOffering $courseOffering, array $filters = []): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyGradeMatrix();
        }

        // Get enrolled students
        $students = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->with('student')
            ->get()
            ->pluck('student')
            ->sortBy('display_name');

        // Apply student filter if provided
        if (! empty($filters['student_ids'])) {
            $students = $students->whereIn('id', $filters['student_ids']);
        }

        // Get assessment components
        $assessmentComponents = $syllabus->assessmentComponents()
            ->with(['details'])
            ->when(! empty($filters['component_ids']), function ($query) use ($filters) {
                return $query->whereIn('id', $filters['component_ids']);
            })
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        // Build grade matrix
        $gradeMatrix = [];
        $componentHeaders = [];

        // Build component headers
        foreach ($assessmentComponents as $component) {
            $componentHeaders[] = [
                'id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'weight' => $component->weight,
                'details' => $component->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'name' => $detail->name,
                        'weight' => $detail->weight,
                    ];
                })->toArray(),
            ];
        }

        // Get all scores with optimized query to avoid N+1 problem
        $allScores = DB::table('assessment_component_detail_scores as acds')
            ->join('assessment_component_details as acd', 'acds.assessment_component_detail_id', '=', 'acd.id')
            ->join('assessment_components as ac', 'acd.assessment_component_id', '=', 'ac.id')
            ->where('ac.syllabus_id', $syllabus->id)
            ->where('acds.course_offering_id', $courseOffering->id)
            ->where('acds.score_status', $filters['score_status'] ?? 'final')
            ->when(! ($filters['include_excluded'] ?? false), function ($query) {
                return $query->where('acds.score_excluded', false);
            })
            ->whereNull('acds.deleted_at')
            ->when(! empty($filters['student_ids']), function ($query) use ($filters) {
                return $query->whereIn('acds.student_id', $filters['student_ids']);
            })
            ->when(! empty($filters['component_ids']), function ($query) use ($filters) {
                return $query->whereIn('ac.id', $filters['component_ids']);
            })
            ->select([
                'acds.id as score_id',
                'acds.student_id',
                'acd.id as detail_id',
                'ac.id as component_id',
                'acds.points_earned',
                'acds.percentage_score',
                'acds.letter_grade',
                'acds.status',
                'acds.score_status',
                'acds.is_late',
                'acds.late_penalty_applied',
                'acds.late_excuse_approved',
                'acds.bonus_points',
                'acds.score_excluded',
                'acds.exclusion_reason',
                'acds.plagiarism_suspected',
                'acds.appeal_requested',
                'acds.graded_at',
                'acd.weight as detail_weight',
                'ac.weight as component_weight',
            ])
            ->get();

        // Index scores by student and detail for efficient lookup
        $scoresIndex = [];
        foreach ($allScores as $score) {
            $scoresIndex[$score->student_id][$score->detail_id] = $score;
        }

        // Build grade matrix with enhanced statistics
        $gradeMatrix = [];
        $classStatistics = [
            'total_weighted_scores' => [],
            'component_averages' => [],
            'missing_scores_count' => 0,
            'excluded_scores_count' => 0,
        ];

        foreach ($students as $student) {
            $studentRow = [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->display_name,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                ],
                'component_scores' => [],
                'weighted_total' => 0,
                'final_percentage' => 0,
                'letter_grade' => null,
                'missing_assessments' => 0,
                'excluded_assessments' => 0,
                'late_submissions' => 0,
                'bonus_points_total' => 0,
                'has_integrity_concerns' => false,
                'has_appeals' => false,
            ];

            $totalWeightedScore = 0;
            $totalWeight = 0;
            $studentMissingCount = 0;
            $studentExcludedCount = 0;
            $studentLateCount = 0;
            $studentBonusTotal = 0;
            $hasIntegrityConcerns = false;
            $hasAppeals = false;

            foreach ($assessmentComponents as $component) {
                $componentScores = [];
                $componentWeightedScore = 0;
                $componentTotalWeight = 0;
                $componentHasScores = false;

                foreach ($component->details as $detail) {
                    $score = $scoresIndex[$student->id][$detail->id] ?? null;
                    $scoreData = null;

                    if ($score) {
                        $componentHasScores = true;

                        // Calculate final score with all adjustments
                        $finalScore = $this->calculateDetailFinalScore($score);

                        $scoreData = [
                            'id' => $score->score_id,
                            'points_earned' => $score->points_earned,
                            'percentage_score' => $score->percentage_score,
                            'final_score' => $finalScore,
                            'letter_grade' => $score->letter_grade,
                            'status' => $score->status,
                            'score_status' => $score->score_status,
                            'is_late' => (bool) $score->is_late,
                            'late_penalty_applied' => $score->late_penalty_applied,
                            'late_excuse_approved' => (bool) $score->late_excuse_approved,
                            'bonus_points' => $score->bonus_points,
                            'score_excluded' => (bool) $score->score_excluded,
                            'exclusion_reason' => $score->exclusion_reason,
                            'plagiarism_suspected' => (bool) $score->plagiarism_suspected,
                            'appeal_requested' => (bool) $score->appeal_requested,
                            'graded_at' => $score->graded_at,
                            'has_adjustments' => $this->scoreHasAdjustments($score),
                        ];

                        // Track student-level statistics
                        if ($score->is_late) {
                            $studentLateCount++;
                        }
                        if ($score->score_excluded) {
                            $studentExcludedCount++;
                        }
                        if ($score->bonus_points > 0) {
                            $studentBonusTotal += $score->bonus_points;
                        }
                        if ($score->plagiarism_suspected) {
                            $hasIntegrityConcerns = true;
                        }
                        if ($score->appeal_requested) {
                            $hasAppeals = true;
                        }

                        // Calculate weighted score for this detail (only if not excluded)
                        if ($finalScore !== null && ! $score->score_excluded) {
                            $componentWeightedScore += ($finalScore * $detail->weight);
                            $componentTotalWeight += $detail->weight;
                        }
                    } else {
                        // Missing score
                        $studentMissingCount++;
                        $scoreData = [
                            'id' => null,
                            'points_earned' => null,
                            'percentage_score' => null,
                            'final_score' => null,
                            'letter_grade' => null,
                            'status' => 'missing',
                            'score_status' => null,
                            'is_late' => false,
                            'late_penalty_applied' => 0,
                            'late_excuse_approved' => false,
                            'bonus_points' => 0,
                            'score_excluded' => false,
                            'exclusion_reason' => null,
                            'plagiarism_suspected' => false,
                            'appeal_requested' => false,
                            'graded_at' => null,
                            'has_adjustments' => false,
                        ];
                    }

                    $componentScores[] = [
                        'detail_id' => $detail->id,
                        'detail_name' => $detail->name,
                        'detail_weight' => $detail->weight,
                        'score' => $scoreData,
                    ];
                }

                // Calculate component average
                $componentAverage = null;
                if ($componentTotalWeight > 0) {
                    $componentAverage = round($componentWeightedScore / $componentTotalWeight, 2);

                    // Add to total weighted score
                    $totalWeightedScore += ($componentAverage * $component->weight);
                    $totalWeight += $component->weight;

                    // Track for class statistics
                    if (! isset($classStatistics['component_averages'][$component->id])) {
                        $classStatistics['component_averages'][$component->id] = [];
                    }
                    $classStatistics['component_averages'][$component->id][] = $componentAverage;
                }

                $studentRow['component_scores'][] = [
                    'component_id' => $component->id,
                    'component_name' => $component->name,
                    'component_type' => $component->type,
                    'component_weight' => $component->weight,
                    'component_average' => $componentAverage,
                    'has_scores' => $componentHasScores,
                    'detail_scores' => $componentScores,
                ];
            }

            // Calculate final percentage and letter grade
            $finalPercentage = $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 2) : 0;

            $studentRow['weighted_total'] = round($totalWeightedScore, 2);
            $studentRow['final_percentage'] = $finalPercentage;
            $studentRow['letter_grade'] = $this->calculateLetterGrade($finalPercentage);
            $studentRow['missing_assessments'] = $studentMissingCount;
            $studentRow['excluded_assessments'] = $studentExcludedCount;
            $studentRow['late_submissions'] = $studentLateCount;
            $studentRow['bonus_points_total'] = $studentBonusTotal;
            $studentRow['has_integrity_concerns'] = $hasIntegrityConcerns;
            $studentRow['has_appeals'] = $hasAppeals;

            // Track for class statistics
            if ($finalPercentage > 0) {
                $classStatistics['total_weighted_scores'][] = $finalPercentage;
            }
            $classStatistics['missing_scores_count'] += $studentMissingCount;
            $classStatistics['excluded_scores_count'] += $studentExcludedCount;

            $gradeMatrix[] = $studentRow;
        }

        // Calculate comprehensive summary statistics
        $summary = $this->calculateMatrixSummary($gradeMatrix, $assessmentComponents->toArray(), $classStatistics);

        return [
            'component_headers' => $componentHeaders,
            'student_grades' => $gradeMatrix,
            'summary' => $summary,
            'filters_applied' => $filters,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate letter grade from percentage.
     */
    private function calculateLetterGrade(float $percentage): ?string
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
     * Calculate class average from grade matrix.
     */
    private function calculateClassAverage(array $gradeMatrix): float
    {
        if (empty($gradeMatrix)) {
            return 0;
        }

        $totalPercentage = array_sum(array_column($gradeMatrix, 'final_percentage'));

        return round($totalPercentage / count($gradeMatrix), 2);
    }

    /**
     * Calculate grade distribution from grade matrix.
     */
    private function calculateGradeDistributionFromMatrix(array $gradeMatrix): array
    {
        $distribution = [
            'A+' => 0,
            'A' => 0,
            'A-' => 0,
            'B+' => 0,
            'B' => 0,
            'B-' => 0,
            'C+' => 0,
            'C' => 0,
            'C-' => 0,
            'D+' => 0,
            'D' => 0,
            'F' => 0,
        ];

        foreach ($gradeMatrix as $studentRow) {
            $letterGrade = $studentRow['letter_grade'];
            if ($letterGrade && isset($distribution[$letterGrade])) {
                $distribution[$letterGrade]++;
            }
        }

        return $distribution;
    }

    /**
     * Get empty grade matrix structure.
     */
    private function getEmptyGradeMatrix(): array
    {
        return [
            'component_headers' => [],
            'student_grades' => [],
            'summary' => [
                'total_students' => 0,
                'total_components' => 0,
                'total_weight' => 0,
                'class_average' => 0,
                'grade_distribution' => [],
            ],
        ];
    }

    /**
     * Calculate final score for a detail with all adjustments.
     *
     * @param  object  $score  Database score object
     */
    private function calculateDetailFinalScore(object $score): ?float
    {
        // Return null if score is excluded from calculations
        if ($score->score_excluded) {
            return null;
        }

        $finalScore = $score->percentage_score ?? 0;

        // Apply late penalty if applicable
        if ($score->is_late && $score->late_penalty_applied > 0 && ! $score->late_excuse_approved) {
            $finalScore -= $score->late_penalty_applied;
        }

        // Add bonus points if applicable
        if ($score->bonus_points > 0) {
            $finalScore += $score->bonus_points;
        }

        // Ensure score is within valid range (0-100)
        return max(0, min(100, $finalScore));
    }

    /**
     * Check if a score has any adjustments applied.
     *
     * @param  object  $score  Database score object
     */
    private function scoreHasAdjustments(object $score): bool
    {
        return ($score->is_late && $score->late_penalty_applied > 0) ||
            $score->bonus_points > 0 ||
            $score->score_excluded ||
            $score->plagiarism_suspected ||
            $score->appeal_requested;
    }

    /**
     * Calculate comprehensive matrix summary statistics.
     */
    private function calculateMatrixSummary(array $gradeMatrix, array $assessmentComponents, array $classStatistics): array
    {
        $totalStudents = count($gradeMatrix);
        $totalComponents = count($assessmentComponents);
        $totalWeight = array_sum(array_column($assessmentComponents, 'weight'));

        // Calculate class average
        $classAverage = 0;
        if (! empty($classStatistics['total_weighted_scores'])) {
            $classAverage = round(array_sum($classStatistics['total_weighted_scores']) / count($classStatistics['total_weighted_scores']), 2);
        }

        // Calculate grade distribution
        $gradeDistribution = $this->calculateGradeDistributionFromMatrix($gradeMatrix);

        // Calculate component averages
        $componentAverages = [];
        foreach ($classStatistics['component_averages'] as $componentId => $averages) {
            if (! empty($averages)) {
                $componentAverages[$componentId] = round(array_sum($averages) / count($averages), 2);
            }
        }

        // Calculate performance metrics
        $performanceMetrics = [
            'students_above_90' => count(array_filter($gradeMatrix, fn($row) => $row['final_percentage'] >= 90)),
            'students_above_80' => count(array_filter($gradeMatrix, fn($row) => $row['final_percentage'] >= 80)),
            'students_above_70' => count(array_filter($gradeMatrix, fn($row) => $row['final_percentage'] >= 70)),
            'students_below_60' => count(array_filter($gradeMatrix, fn($row) => $row['final_percentage'] < 60)),
            'students_with_missing' => count(array_filter($gradeMatrix, fn($row) => $row['missing_assessments'] > 0)),
            'students_with_late' => count(array_filter($gradeMatrix, fn($row) => $row['late_submissions'] > 0)),
            'students_with_bonus' => count(array_filter($gradeMatrix, fn($row) => $row['bonus_points_total'] > 0)),
        ];

        return [
            'total_students' => $totalStudents,
            'total_components' => $totalComponents,
            'total_weight' => $totalWeight,
            'weight_complete' => $totalWeight == 100.0,
            'class_average' => $classAverage,
            'grade_distribution' => $gradeDistribution,
            'component_averages' => $componentAverages,
            'performance_metrics' => $performanceMetrics,
            'submission_statistics' => [
                'total_missing_scores' => $classStatistics['missing_scores_count'],
                'total_excluded_scores' => $classStatistics['excluded_scores_count'],
            ],
        ];
    }

    /**
     * Get empty completion statistics structure.
     */
    private function getEmptyCompletionStatistics(): array
    {
        return [
            'overview' => [
                'total_enrolled_students' => 0,
                'total_assessment_details' => 0,
                'expected_submissions' => 0,
                'actual_submissions' => 0,
                'missing_submissions' => 0,
                'overall_completion_rate' => 0,
            ],
            'submission_status' => [
                'submitted' => 0,
                'graded' => 0,
                'draft' => 0,
                'provisional' => 0,
                'late' => 0,
                'excluded' => 0,
                'missing' => 0,
            ],
            'component_completion_rates' => [],
        ];
    }

    /**
     * Identify at-risk students based on performance metrics.
     */
    public function identifyAtRiskStudents(CourseOffering $courseOffering, array $criteria = []): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyAtRiskStudents();
        }

        // Default criteria for at-risk identification
        $defaultCriteria = [
            'low_score_threshold' => 60.0, // Below 60% average
            'missing_submissions_threshold' => 2, // More than 2 missing submissions
            'late_submissions_threshold' => 3, // More than 3 late submissions
            'failing_components_threshold' => 2, // Failing in 2 or more components
            'include_integrity_concerns' => true,
            'include_appeal_requests' => true,
        ];

        $criteria = array_merge($defaultCriteria, $criteria);

        // Get enrolled students with their performance data
        $students = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->with('student')
            ->get()
            ->pluck('student');

        $assessmentComponents = $syllabus->assessmentComponents()
            ->with(['details'])
            ->get();

        $atRiskStudents = [];
        $interventionRecommendations = [];

        foreach ($students as $student) {
            $riskFactors = [];
            $riskScore = 0;
            $studentPerformance = $this->calculateStudentPerformanceMetrics($courseOffering, $student, $assessmentComponents);

            // Check low average score
            if ($studentPerformance['weighted_average'] < $criteria['low_score_threshold']) {
                $riskFactors[] = [
                    'type' => 'low_average_score',
                    'severity' => 'high',
                    'description' => "Average score ({$studentPerformance['weighted_average']}%) below threshold ({$criteria['low_score_threshold']}%)",
                    'value' => $studentPerformance['weighted_average'],
                    'threshold' => $criteria['low_score_threshold'],
                ];
                $riskScore += 30;
            }

            // Check missing submissions
            if ($studentPerformance['missing_submissions'] > $criteria['missing_submissions_threshold']) {
                $riskFactors[] = [
                    'type' => 'excessive_missing_submissions',
                    'severity' => 'high',
                    'description' => "{$studentPerformance['missing_submissions']} missing submissions (threshold: {$criteria['missing_submissions_threshold']})",
                    'value' => $studentPerformance['missing_submissions'],
                    'threshold' => $criteria['missing_submissions_threshold'],
                ];
                $riskScore += 25;
            }

            // Check late submissions
            if ($studentPerformance['late_submissions'] > $criteria['late_submissions_threshold']) {
                $riskFactors[] = [
                    'type' => 'excessive_late_submissions',
                    'severity' => 'medium',
                    'description' => "{$studentPerformance['late_submissions']} late submissions (threshold: {$criteria['late_submissions_threshold']})",
                    'value' => $studentPerformance['late_submissions'],
                    'threshold' => $criteria['late_submissions_threshold'],
                ];
                $riskScore += 15;
            }

            // Check failing components
            if ($studentPerformance['failing_components'] >= $criteria['failing_components_threshold']) {
                $riskFactors[] = [
                    'type' => 'multiple_failing_components',
                    'severity' => 'high',
                    'description' => "Failing in {$studentPerformance['failing_components']} components (threshold: {$criteria['failing_components_threshold']})",
                    'value' => $studentPerformance['failing_components'],
                    'threshold' => $criteria['failing_components_threshold'],
                ];
                $riskScore += 35;
            }

            // Check academic integrity concerns
            if ($criteria['include_integrity_concerns'] && $studentPerformance['integrity_concerns'] > 0) {
                $riskFactors[] = [
                    'type' => 'academic_integrity_concerns',
                    'severity' => 'critical',
                    'description' => "{$studentPerformance['integrity_concerns']} academic integrity concern(s)",
                    'value' => $studentPerformance['integrity_concerns'],
                    'threshold' => 0,
                ];
                $riskScore += 40;
            }

            // Check appeal requests
            if ($criteria['include_appeal_requests'] && $studentPerformance['appeal_requests'] > 0) {
                $riskFactors[] = [
                    'type' => 'grade_appeals',
                    'severity' => 'medium',
                    'description' => "{$studentPerformance['appeal_requests']} grade appeal(s) requested",
                    'value' => $studentPerformance['appeal_requests'],
                    'threshold' => 0,
                ];
                $riskScore += 10;
            }

            // Check declining performance trend
            $performanceTrend = $this->calculatePerformanceTrend($courseOffering, $student, $assessmentComponents);
            if ($performanceTrend['trend'] === 'declining' && $performanceTrend['decline_rate'] > 10) {
                $riskFactors[] = [
                    'type' => 'declining_performance',
                    'severity' => 'medium',
                    'description' => "Performance declining by {$performanceTrend['decline_rate']}% over recent assessments",
                    'value' => $performanceTrend['decline_rate'],
                    'threshold' => 10,
                ];
                $riskScore += 20;
            }

            // If student has risk factors, add to at-risk list
            if (! empty($riskFactors)) {
                $riskLevel = $this->determineRiskLevel($riskScore);

                $atRiskStudents[] = [
                    'student' => [
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'name' => $student->display_name,
                        'email' => $student->email,
                    ],
                    'risk_score' => $riskScore,
                    'risk_level' => $riskLevel,
                    'risk_factors' => $riskFactors,
                    'performance_metrics' => $studentPerformance,
                    'performance_trend' => $performanceTrend,
                    'recommended_interventions' => $this->generateInterventionRecommendations($riskFactors, $studentPerformance),
                    'last_updated' => now()->toISOString(),
                ];
            }
        }

        // Sort by risk score (highest first)
        usort($atRiskStudents, function ($a, $b) {
            return $b['risk_score'] <=> $a['risk_score'];
        });

        return [
            'total_students' => $students->count(),
            'at_risk_count' => count($atRiskStudents),
            'risk_distribution' => $this->calculateRiskDistribution($atRiskStudents),
            'students' => $atRiskStudents,
            'criteria_used' => $criteria,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Identify exceptional performing students.
     */
    public function identifyExceptionalStudents(CourseOffering $courseOffering, array $criteria = []): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyExceptionalStudents();
        }

        // Default criteria for exceptional performance identification
        $defaultCriteria = [
            'high_score_threshold' => 90.0, // Above 90% average
            'consistency_threshold' => 85.0, // No component below 85%
            'improvement_threshold' => 10.0, // 10% improvement over time
            'zero_late_submissions' => true,
            'zero_missing_submissions' => true,
            'exclude_integrity_concerns' => true,
        ];

        $criteria = array_merge($defaultCriteria, $criteria);

        // Get enrolled students with their performance data
        $students = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->with('student')
            ->get()
            ->pluck('student');

        $assessmentComponents = $syllabus->assessmentComponents()
            ->with(['details'])
            ->get();

        $exceptionalStudents = [];

        foreach ($students as $student) {
            $excellenceFactors = [];
            $excellenceScore = 0;
            $studentPerformance = $this->calculateStudentPerformanceMetrics($courseOffering, $student, $assessmentComponents);

            // Check high average score
            if ($studentPerformance['weighted_average'] >= $criteria['high_score_threshold']) {
                $excellenceFactors[] = [
                    'type' => 'high_average_score',
                    'description' => "Exceptional average score: {$studentPerformance['weighted_average']}%",
                    'value' => $studentPerformance['weighted_average'],
                    'threshold' => $criteria['high_score_threshold'],
                ];
                $excellenceScore += 30;
            }

            // Check consistency across components
            if ($studentPerformance['lowest_component_score'] >= $criteria['consistency_threshold']) {
                $excellenceFactors[] = [
                    'type' => 'consistent_performance',
                    'description' => "Consistent performance across all components (lowest: {$studentPerformance['lowest_component_score']}%)",
                    'value' => $studentPerformance['lowest_component_score'],
                    'threshold' => $criteria['consistency_threshold'],
                ];
                $excellenceScore += 25;
            }

            // Check for zero late submissions
            if ($criteria['zero_late_submissions'] && $studentPerformance['late_submissions'] === 0) {
                $excellenceFactors[] = [
                    'type' => 'punctual_submissions',
                    'description' => 'All submissions on time',
                    'value' => 0,
                    'threshold' => 0,
                ];
                $excellenceScore += 15;
            }

            // Check for zero missing submissions
            if ($criteria['zero_missing_submissions'] && $studentPerformance['missing_submissions'] === 0) {
                $excellenceFactors[] = [
                    'type' => 'complete_submissions',
                    'description' => 'All assessments completed',
                    'value' => 0,
                    'threshold' => 0,
                ];
                $excellenceScore += 20;
            }

            // Check for no integrity concerns
            if ($criteria['exclude_integrity_concerns'] && $studentPerformance['integrity_concerns'] === 0) {
                $excellenceFactors[] = [
                    'type' => 'academic_integrity',
                    'description' => 'No academic integrity concerns',
                    'value' => 0,
                    'threshold' => 0,
                ];
                $excellenceScore += 10;
            }

            // Check improving performance trend
            $performanceTrend = $this->calculatePerformanceTrend($courseOffering, $student, $assessmentComponents);
            if ($performanceTrend['trend'] === 'improving' && $performanceTrend['improvement_rate'] >= $criteria['improvement_threshold']) {
                $excellenceFactors[] = [
                    'type' => 'improving_performance',
                    'description' => "Performance improving by {$performanceTrend['improvement_rate']}% over recent assessments",
                    'value' => $performanceTrend['improvement_rate'],
                    'threshold' => $criteria['improvement_threshold'],
                ];
                $excellenceScore += 20;
            }

            // If student meets exceptional criteria, add to list
            if ($excellenceScore >= 50) { // Minimum threshold for exceptional status
                $exceptionalStudents[] = [
                    'student' => [
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'name' => $student->display_name,
                        'email' => $student->email,
                    ],
                    'excellence_score' => $excellenceScore,
                    'excellence_factors' => $excellenceFactors,
                    'performance_metrics' => $studentPerformance,
                    'performance_trend' => $performanceTrend,
                    'recognition_suggestions' => $this->generateRecognitionSuggestions($excellenceFactors, $studentPerformance),
                    'last_updated' => now()->toISOString(),
                ];
            }
        }

        // Sort by excellence score (highest first)
        usort($exceptionalStudents, function ($a, $b) {
            return $b['excellence_score'] <=> $a['excellence_score'];
        });

        return [
            'total_students' => $students->count(),
            'exceptional_count' => count($exceptionalStudents),
            'students' => $exceptionalStudents,
            'criteria_used' => $criteria,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Monitor academic integrity issues across the course.
     */
    public function monitorAcademicIntegrityIssues(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyIntegrityMonitoring();
        }

        $componentIds = $syllabus->assessmentComponents()->pluck('id');

        // Get all integrity-related scores
        $integrityScores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where(function ($query) {
                $query->where('plagiarism_suspected', true)
                    ->orWhere('appeal_requested', true)
                    ->orWhereNotNull('plagiarism_score')
                    ->orWhereNotNull('integrity_status');
            })
            ->with(['student', 'assessmentComponentDetail.assessmentComponent'])
            ->get();

        // Group by student
        $studentIntegrityIssues = [];
        $componentIntegrityStats = [];
        $overallStats = [
            'total_flagged_submissions' => 0,
            'unique_students_flagged' => 0,
            'pending_investigations' => 0,
            'resolved_cases' => 0,
            'appeal_requests' => 0,
            'average_plagiarism_score' => 0,
        ];

        foreach ($integrityScores as $score) {
            $studentId = $score->student_id;
            $componentId = $score->assessmentComponentDetail->assessmentComponent->id;

            // Track student-level issues
            if (! isset($studentIntegrityIssues[$studentId])) {
                $studentIntegrityIssues[$studentId] = [
                    'student' => [
                        'id' => $score->student->id,
                        'student_id' => $score->student->student_id,
                        'name' => $score->student->display_name,
                        'email' => $score->student->email,
                    ],
                    'total_flags' => 0,
                    'plagiarism_cases' => 0,
                    'appeal_requests' => 0,
                    'highest_plagiarism_score' => 0,
                    'cases' => [],
                    'risk_level' => 'low',
                ];
            }

            // Track component-level stats
            if (! isset($componentIntegrityStats[$componentId])) {
                $componentIntegrityStats[$componentId] = [
                    'component' => [
                        'id' => $score->assessmentComponentDetail->assessmentComponent->id,
                        'name' => $score->assessmentComponentDetail->assessmentComponent->name,
                        'type' => $score->assessmentComponentDetail->assessmentComponent->type,
                    ],
                    'total_flags' => 0,
                    'plagiarism_cases' => 0,
                    'appeal_requests' => 0,
                    'average_plagiarism_score' => 0,
                    'plagiarism_scores' => [],
                ];
            }

            // Process individual case
            $case = [
                'score_id' => $score->id,
                'assessment_detail' => [
                    'id' => $score->assessmentComponentDetail->id,
                    'name' => $score->assessmentComponentDetail->name,
                ],
                'plagiarism_suspected' => (bool) $score->plagiarism_suspected,
                'plagiarism_score' => $score->plagiarism_score,
                'plagiarism_notes' => $score->plagiarism_notes,
                'integrity_status' => $score->integrity_status,
                'appeal_requested' => (bool) $score->appeal_requested,
                'appeal_reason' => $score->appeal_reason,
                'appeal_status' => $score->appeal_status,
                'flagged_at' => $score->updated_at,
            ];

            $studentIntegrityIssues[$studentId]['cases'][] = $case;
            $studentIntegrityIssues[$studentId]['total_flags']++;

            if ($score->plagiarism_suspected) {
                $studentIntegrityIssues[$studentId]['plagiarism_cases']++;
                $componentIntegrityStats[$componentId]['plagiarism_cases']++;
                $overallStats['total_flagged_submissions']++;
            }

            if ($score->appeal_requested) {
                $studentIntegrityIssues[$studentId]['appeal_requests']++;
                $componentIntegrityStats[$componentId]['appeal_requests']++;
                $overallStats['appeal_requests']++;
            }

            if ($score->plagiarism_score) {
                $studentIntegrityIssues[$studentId]['highest_plagiarism_score'] = max(
                    $studentIntegrityIssues[$studentId]['highest_plagiarism_score'],
                    $score->plagiarism_score
                );
                $componentIntegrityStats[$componentId]['plagiarism_scores'][] = $score->plagiarism_score;
            }

            $componentIntegrityStats[$componentId]['total_flags']++;

            // Update overall stats
            if ($score->integrity_status === 'under_investigation') {
                $overallStats['pending_investigations']++;
            } elseif (in_array($score->integrity_status, ['resolved', 'no_violation'])) {
                $overallStats['resolved_cases']++;
            }
        }

        // Calculate risk levels and averages
        foreach ($studentIntegrityIssues as &$studentData) {
            $studentData['risk_level'] = $this->calculateIntegrityRiskLevel($studentData);
        }

        foreach ($componentIntegrityStats as &$componentData) {
            if (! empty($componentData['plagiarism_scores'])) {
                $componentData['average_plagiarism_score'] = round(
                    array_sum($componentData['plagiarism_scores']) / count($componentData['plagiarism_scores']),
                    2
                );
            }
        }

        $overallStats['unique_students_flagged'] = count($studentIntegrityIssues);

        if ($overallStats['total_flagged_submissions'] > 0) {
            $allPlagiarismScores = $integrityScores->whereNotNull('plagiarism_score')->pluck('plagiarism_score');
            $overallStats['average_plagiarism_score'] = $allPlagiarismScores->isNotEmpty()
                ? round($allPlagiarismScores->average(), 2)
                : 0;
        }

        return [
            'overall_statistics' => $overallStats,
            'student_integrity_issues' => array_values($studentIntegrityIssues),
            'component_integrity_statistics' => array_values($componentIntegrityStats),
            'recommendations' => $this->generateIntegrityRecommendations($overallStats, $studentIntegrityIssues),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate performance analytics data for charts and visualizations.
     */
    public function generatePerformanceAnalytics(CourseOffering $courseOffering, array $options = []): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyPerformanceAnalytics();
        }

        $defaultOptions = [
            'include_grade_distribution' => true,
            'include_component_comparison' => true,
            'include_performance_trends' => true,
            'include_submission_patterns' => true,
            'histogram_bins' => 10,
            'trend_period_days' => 30,
        ];

        $options = array_merge($defaultOptions, $options);

        $analytics = [
            'course_info' => [
                'id' => $courseOffering->id,
                'name' => $courseOffering->course_name ?? 'Unknown Course',
                'semester' => $courseOffering->semester->name ?? 'Unknown Semester',
                'generated_at' => now()->toISOString(),
            ],
        ];

        if ($options['include_grade_distribution']) {
            $analytics['grade_distribution'] = $this->generateGradeDistributionData($courseOffering, $options['histogram_bins']);
        }

        if ($options['include_component_comparison']) {
            $analytics['component_comparison'] = $this->generateComponentComparisonData($courseOffering);
        }

        if ($options['include_performance_trends']) {
            $analytics['performance_trends'] = $this->generatePerformanceTrendsData($courseOffering, $options['trend_period_days']);
        }

        if ($options['include_submission_patterns']) {
            $analytics['submission_patterns'] = $this->generateSubmissionPatternsData($courseOffering);
        }

        return $analytics;
    }

    /**
     * Generate grade distribution histogram data for visualization.
     */
    public function generateGradeDistributionData(CourseOffering $courseOffering, int $bins = 10): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyGradeDistribution();
        }

        $componentIds = $syllabus->assessmentComponents()->pluck('id');

        // Get all final scores
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_status', 'final')
            ->where('score_excluded', false)
            ->whereNotNull('percentage_score')
            ->get();

        if ($scores->isEmpty()) {
            return $this->getEmptyGradeDistribution();
        }

        $percentageScores = $scores->pluck('percentage_score');

        // Generate histogram data
        $binSize = 100 / $bins;
        $histogram = [];

        for ($i = 0; $i < $bins; $i++) {
            $rangeMin = $i * $binSize;
            $rangeMax = ($i + 1) * $binSize;

            // Handle the last bin to include 100%
            if ($i === $bins - 1) {
                $rangeMax = 100;
            }

            $count = $percentageScores->filter(function ($score) use ($rangeMin, $rangeMax, $i, $bins) {
                if ($i === $bins - 1) {
                    return $score >= $rangeMin && $score <= $rangeMax;
                }

                return $score >= $rangeMin && $score < $rangeMax;
            })->count();

            $histogram[] = [
                'bin' => $i + 1,
                'range_min' => round($rangeMin, 1),
                'range_max' => round($rangeMax, 1),
                'range_label' => round($rangeMin, 1) . '-' . round($rangeMax, 1) . '%',
                'count' => $count,
                'percentage' => $scores->count() > 0 ? round(($count / $scores->count()) * 100, 2) : 0,
                'density' => $scores->count() > 0 ? round($count / ($scores->count() * $binSize), 4) : 0,
            ];
        }

        // Calculate letter grade distribution
        $letterGradeDistribution = [
            'A' => ['min' => 90, 'max' => 100, 'count' => 0, 'percentage' => 0],
            'B' => ['min' => 80, 'max' => 89.99, 'count' => 0, 'percentage' => 0],
            'C' => ['min' => 70, 'max' => 79.99, 'count' => 0, 'percentage' => 0],
            'D' => ['min' => 60, 'max' => 69.99, 'count' => 0, 'percentage' => 0],
            'F' => ['min' => 0, 'max' => 59.99, 'count' => 0, 'percentage' => 0],
        ];

        foreach ($percentageScores as $score) {
            foreach ($letterGradeDistribution as $grade => &$range) {
                if ($score >= $range['min'] && $score <= $range['max']) {
                    $range['count']++;
                    break;
                }
            }
        }

        // Calculate percentages for letter grades
        foreach ($letterGradeDistribution as &$range) {
            $range['percentage'] = $scores->count() > 0 ? round(($range['count'] / $scores->count()) * 100, 2) : 0;
        }

        // Calculate statistical measures
        $statistics = [
            'total_scores' => $scores->count(),
            'mean' => round($percentageScores->average(), 2),
            'median' => $this->calculateMedian($percentageScores->toArray()),
            'mode' => $this->calculateMode($percentageScores->toArray()),
            'standard_deviation' => $this->calculateStandardDeviation($percentageScores->toArray()),
            'variance' => $this->calculateVariance($percentageScores->toArray()),
            'skewness' => $this->calculateSkewness($percentageScores->toArray()),
            'kurtosis' => $this->calculateKurtosis($percentageScores->toArray()),
            'range' => [
                'min' => $percentageScores->min(),
                'max' => $percentageScores->max(),
                'span' => $percentageScores->max() - $percentageScores->min(),
            ],
            'quartiles' => $this->calculateQuartiles($percentageScores->toArray()),
            'percentiles' => $this->calculatePercentiles($percentageScores->toArray()),
        ];

        return [
            'histogram' => $histogram,
            'letter_grade_distribution' => $letterGradeDistribution,
            'statistics' => $statistics,
            'chart_data' => [
                'histogram_chart' => $this->formatHistogramForChart($histogram),
                'pie_chart' => $this->formatLetterGradesForPieChart($letterGradeDistribution),
                'box_plot' => $this->formatDataForBoxPlot($statistics),
            ],
        ];
    }

    /**
     * Generate component performance comparison data.
     */
    public function generateComponentComparisonData(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyComponentComparison();
        }

        $assessmentComponents = $syllabus->assessmentComponents()
            ->with(['details'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $componentComparison = [];
        $overallComparison = [
            'highest_performing_component' => null,
            'lowest_performing_component' => null,
            'most_consistent_component' => null,
            'least_consistent_component' => null,
            'average_difficulty_ranking' => [],
        ];

        foreach ($assessmentComponents as $component) {
            $componentScores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($component) {
                $query->where('assessment_component_id', $component->id);
            })
                ->where('course_offering_id', $courseOffering->id)
                ->where('score_status', 'final')
                ->where('score_excluded', false)
                ->whereNotNull('percentage_score')
                ->get();

            if ($componentScores->isEmpty()) {
                continue;
            }

            $percentageScores = $componentScores->pluck('percentage_score');
            $statistics = [
                'count' => $componentScores->count(),
                'mean' => round($percentageScores->average(), 2),
                'median' => $this->calculateMedian($percentageScores->toArray()),
                'standard_deviation' => $this->calculateStandardDeviation($percentageScores->toArray()),
                'min' => $percentageScores->min(),
                'max' => $percentageScores->max(),
                'range' => $percentageScores->max() - $percentageScores->min(),
                'quartiles' => $this->calculateQuartiles($percentageScores->toArray()),
            ];

            // Calculate grade distribution for this component
            $gradeDistribution = [
                'A' => 0,
                'B' => 0,
                'C' => 0,
                'D' => 0,
                'F' => 0,
            ];

            foreach ($percentageScores as $score) {
                if ($score >= 90) {
                    $gradeDistribution['A']++;
                } elseif ($score >= 80) {
                    $gradeDistribution['B']++;
                } elseif ($score >= 70) {
                    $gradeDistribution['C']++;
                } elseif ($score >= 60) {
                    $gradeDistribution['D']++;
                } else {
                    $gradeDistribution['F']++;
                }
            }

            // Calculate difficulty metrics
            $difficultyMetrics = [
                'difficulty_score' => round(100 - $statistics['mean'], 2), // Higher score = more difficult
                'discrimination_index' => $this->calculateDiscriminationIndex($percentageScores->toArray()),
                'reliability_coefficient' => $this->calculateReliabilityCoefficient($percentageScores->toArray()),
            ];

            $componentData = [
                'component' => [
                    'id' => $component->id,
                    'name' => $component->name,
                    'type' => $component->type,
                    'weight' => $component->weight,
                    'details_count' => $component->details->count(),
                ],
                'statistics' => $statistics,
                'grade_distribution' => $gradeDistribution,
                'difficulty_metrics' => $difficultyMetrics,
                'submission_info' => [
                    'total_expected' => $courseOffering->courseRegistrations()
                        ->whereIn('registration_status', ['registered', 'confirmed'])
                        ->count() * $component->details->count(),
                    'actual_submissions' => $componentScores->count(),
                    'completion_rate' => $this->calculateComponentCompletionRate(
                        $courseOffering,
                        $component,
                        $courseOffering->courseRegistrations()
                            ->whereIn('registration_status', ['registered', 'confirmed'])
                            ->count()
                    )['completion_rate'],
                ],
            ];

            $componentComparison[] = $componentData;
        }

        // Calculate overall comparison metrics
        if (! empty($componentComparison)) {
            $overallComparison = $this->calculateOverallComponentComparison($componentComparison);
        }

        return [
            'components' => $componentComparison,
            'overall_comparison' => $overallComparison,
            'chart_data' => [
                'performance_comparison' => $this->formatComponentsForPerformanceChart($componentComparison),
                'difficulty_ranking' => $this->formatComponentsForDifficultyChart($componentComparison),
                'consistency_comparison' => $this->formatComponentsForConsistencyChart($componentComparison),
            ],
        ];
    }

    /**
     * Generate performance trends data over time.
     */
    public function generatePerformanceTrendsData(CourseOffering $courseOffering, int $periodDays = 30): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptyPerformanceTrends();
        }

        $componentIds = $syllabus->assessmentComponents()->pluck('id');
        $startDate = now()->subDays($periodDays);

        // Get scores with grading timestamps
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_status', 'final')
            ->where('score_excluded', false)
            ->whereNotNull('percentage_score')
            ->whereNotNull('graded_at')
            ->where('graded_at', '>=', $startDate)
            ->with(['assessmentComponentDetail.assessmentComponent'])
            ->orderBy('graded_at')
            ->get();

        if ($scores->isEmpty()) {
            return $this->getEmptyPerformanceTrends();
        }

        // Group scores by date
        $dailyPerformance = [];
        $weeklyPerformance = [];
        $componentTrends = [];

        foreach ($scores as $score) {
            $date = $score->graded_at->format('Y-m-d');
            $week = $score->graded_at->format('Y-W');
            $componentId = $score->assessmentComponentDetail->assessmentComponent->id;

            // Daily performance
            if (! isset($dailyPerformance[$date])) {
                $dailyPerformance[$date] = [
                    'date' => $date,
                    'scores' => [],
                    'count' => 0,
                    'average' => 0,
                ];
            }
            $dailyPerformance[$date]['scores'][] = $score->percentage_score;
            $dailyPerformance[$date]['count']++;

            // Weekly performance
            if (! isset($weeklyPerformance[$week])) {
                $weeklyPerformance[$week] = [
                    'week' => $week,
                    'scores' => [],
                    'count' => 0,
                    'average' => 0,
                ];
            }
            $weeklyPerformance[$week]['scores'][] = $score->percentage_score;
            $weeklyPerformance[$week]['count']++;

            // Component trends
            if (! isset($componentTrends[$componentId])) {
                $componentTrends[$componentId] = [
                    'component' => [
                        'id' => $score->assessmentComponentDetail->assessmentComponent->id,
                        'name' => $score->assessmentComponentDetail->assessmentComponent->name,
                    ],
                    'daily_scores' => [],
                ];
            }
            if (! isset($componentTrends[$componentId]['daily_scores'][$date])) {
                $componentTrends[$componentId]['daily_scores'][$date] = [];
            }
            $componentTrends[$componentId]['daily_scores'][$date][] = $score->percentage_score;
        }

        // Calculate averages for daily performance
        foreach ($dailyPerformance as &$day) {
            $day['average'] = round(array_sum($day['scores']) / count($day['scores']), 2);
            $day['median'] = $this->calculateMedian($day['scores']);
            $day['std_dev'] = $this->calculateStandardDeviation($day['scores']);
        }

        // Calculate averages for weekly performance
        foreach ($weeklyPerformance as &$week) {
            $week['average'] = round(array_sum($week['scores']) / count($week['scores']), 2);
            $week['median'] = $this->calculateMedian($week['scores']);
            $week['std_dev'] = $this->calculateStandardDeviation($week['scores']);
        }

        // Calculate component trend averages
        foreach ($componentTrends as &$trend) {
            $trend['daily_averages'] = [];
            foreach ($trend['daily_scores'] as $date => $scores) {
                $trend['daily_averages'][$date] = round(array_sum($scores) / count($scores), 2);
            }
        }

        // Calculate overall trend analysis
        $trendAnalysis = $this->calculateOverallTrendAnalysis($dailyPerformance);

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'days' => $periodDays,
            ],
            'daily_performance' => array_values($dailyPerformance),
            'weekly_performance' => array_values($weeklyPerformance),
            'component_trends' => array_values($componentTrends),
            'trend_analysis' => $trendAnalysis,
            'chart_data' => [
                'daily_trend_line' => $this->formatDailyTrendForChart($dailyPerformance),
                'weekly_trend_line' => $this->formatWeeklyTrendForChart($weeklyPerformance),
                'component_trends' => $this->formatComponentTrendsForChart($componentTrends),
            ],
        ];
    }

    /**
     * Generate submission patterns data for analysis.
     */
    public function generateSubmissionPatternsData(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return $this->getEmptySubmissionPatterns();
        }

        $componentIds = $syllabus->assessmentComponents()->pluck('id');

        // Get all scores with submission data
        $scores = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($componentIds) {
            $query->whereIn('assessment_component_id', $componentIds);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->whereNotNull('submitted_at')
            ->with(['assessmentComponentDetail.assessmentComponent'])
            ->get();

        if ($scores->isEmpty()) {
            return $this->getEmptySubmissionPatterns();
        }

        // Analyze submission timing patterns
        $hourlyPattern = array_fill(0, 24, 0);
        $dailyPattern = array_fill(0, 7, 0); // 0 = Sunday, 6 = Saturday
        $lateSubmissions = [];
        $submissionGaps = [];

        foreach ($scores as $score) {
            $submissionTime = $score->submitted_at;

            // Hourly pattern
            $hourlyPattern[$submissionTime->hour]++;

            // Daily pattern (day of week)
            $dailyPattern[$submissionTime->dayOfWeek]++;

            // Late submission analysis
            if ($score->is_late) {
                $lateSubmissions[] = [
                    'score_id' => $score->id,
                    'minutes_late' => $score->minutes_late,
                    'penalty_applied' => $score->late_penalty_applied,
                    'component' => $score->assessmentComponentDetail->assessmentComponent->name,
                ];
            }
        }

        // Calculate submission timing statistics
        $timingStats = [
            'peak_submission_hour' => array_keys($hourlyPattern, max($hourlyPattern))[0],
            'peak_submission_day' => array_keys($dailyPattern, max($dailyPattern))[0],
            'total_submissions' => $scores->count(),
            'late_submissions' => count($lateSubmissions),
            'late_submission_rate' => $scores->count() > 0 ? round((count($lateSubmissions) / $scores->count()) * 100, 2) : 0,
        ];

        return [
            'timing_patterns' => [
                'hourly_distribution' => $this->formatHourlyPattern($hourlyPattern),
                'daily_distribution' => $this->formatDailyPattern($dailyPattern),
            ],
            'late_submission_analysis' => [
                'statistics' => $timingStats,
                'late_submissions' => $lateSubmissions,
                'average_lateness' => count($lateSubmissions) > 0 ?
                    round(collect($lateSubmissions)->avg('minutes_late'), 2) : 0,
            ],
            'chart_data' => [
                'hourly_heatmap' => $this->formatHourlyHeatmap($hourlyPattern),
                'daily_bar_chart' => $this->formatDailyBarChart($dailyPattern),
                'lateness_distribution' => $this->formatLatenessDistribution($lateSubmissions),
            ],
        ];
    }

    // Helper methods for statistical calculations

    /**
     * Calculate mode of an array of numbers.
     */
    private function calculateMode(array $numbers): ?float
    {
        if (empty($numbers)) {
            return null;
        }

        $frequency = array_count_values($numbers);
        $maxFreq = max($frequency);
        $modes = array_keys($frequency, $maxFreq);

        return count($modes) === 1 ? (float) $modes[0] : null;
    }

    /**
     * Calculate variance of an array of numbers.
     */
    private function calculateVariance(array $numbers): float
    {
        if (empty($numbers)) {
            return 0;
        }

        $mean = array_sum($numbers) / count($numbers);
        $squaredDifferences = array_map(function ($number) use ($mean) {
            return pow($number - $mean, 2);
        }, $numbers);

        return array_sum($squaredDifferences) / count($numbers);
    }

    /**
     * Calculate skewness of an array of numbers.
     */
    private function calculateSkewness(array $numbers): float
    {
        if (count($numbers) < 3) {
            return 0;
        }

        $mean = array_sum($numbers) / count($numbers);
        $stdDev = $this->calculateStandardDeviation($numbers);

        if ($stdDev == 0) {
            return 0;
        }

        $sum = 0;
        foreach ($numbers as $number) {
            $sum += pow(($number - $mean) / $stdDev, 3);
        }

        return $sum / count($numbers);
    }

    /**
     * Calculate kurtosis of an array of numbers.
     */
    private function calculateKurtosis(array $numbers): float
    {
        if (count($numbers) < 4) {
            return 0;
        }

        $mean = array_sum($numbers) / count($numbers);
        $stdDev = $this->calculateStandardDeviation($numbers);

        if ($stdDev == 0) {
            return 0;
        }

        $sum = 0;
        foreach ($numbers as $number) {
            $sum += pow(($number - $mean) / $stdDev, 4);
        }

        return ($sum / count($numbers)) - 3; // Excess kurtosis
    }

    /**
     * Calculate percentiles for an array of numbers.
     */
    private function calculatePercentiles(array $numbers): array
    {
        if (empty($numbers)) {
            return [];
        }

        sort($numbers);
        $count = count($numbers);

        $percentiles = [];
        for ($p = 10; $p <= 90; $p += 10) {
            $index = ($p / 100) * ($count - 1);
            $lower = floor($index);
            $upper = ceil($index);

            if ($lower == $upper) {
                $percentiles["p{$p}"] = $numbers[$lower];
            } else {
                $percentiles["p{$p}"] = $numbers[$lower] + (($index - $lower) * ($numbers[$upper] - $numbers[$lower]));
            }
        }

        return $percentiles;
    }

    /**
     * Calculate discrimination index for assessment difficulty.
     */
    private function calculateDiscriminationIndex(array $scores): float
    {
        if (count($scores) < 10) {
            return 0;
        }

        sort($scores);
        $count = count($scores);
        $topGroup = array_slice($scores, (int) ($count * 0.73)); // Top 27%
        $bottomGroup = array_slice($scores, 0, (int) ($count * 0.27)); // Bottom 27%

        $topAvg = array_sum($topGroup) / count($topGroup);
        $bottomAvg = array_sum($bottomGroup) / count($bottomGroup);

        return round(($topAvg - $bottomAvg) / 100, 3);
    }

    /**
     * Calculate reliability coefficient.
     */
    private function calculateReliabilityCoefficient(array $scores): float
    {
        if (count($scores) < 5) {
            return 0;
        }

        $variance = $this->calculateVariance($scores);
        $mean = array_sum($scores) / count($scores);

        if ($mean == 0) {
            return 0;
        }

        return round($variance / ($mean * (100 - $mean)), 3);
    }

    // Chart formatting methods

    /**
     * Format histogram data for chart visualization.
     */
    private function formatHistogramForChart(array $histogram): array
    {
        return array_map(function ($bin) {
            return [
                'x' => $bin['range_label'],
                'y' => $bin['count'],
                'percentage' => $bin['percentage'],
            ];
        }, $histogram);
    }

    /**
     * Format letter grades for pie chart.
     */
    private function formatLetterGradesForPieChart(array $letterGrades): array
    {
        return array_map(function ($grade, $data) {
            return [
                'label' => $grade,
                'value' => $data['count'],
                'percentage' => $data['percentage'],
            ];
        }, array_keys($letterGrades), $letterGrades);
    }

    /**
     * Format data for box plot visualization.
     */
    private function formatDataForBoxPlot(array $statistics): array
    {
        return [
            'min' => $statistics['range']['min'],
            'q1' => $statistics['quartiles']['q1'],
            'median' => $statistics['median'],
            'q3' => $statistics['quartiles']['q3'],
            'max' => $statistics['range']['max'],
            'mean' => $statistics['mean'],
            'outliers' => [], // Could be calculated if needed
        ];
    }

    /**
     * Format components for performance comparison chart.
     */
    private function formatComponentsForPerformanceChart(array $components): array
    {
        return array_map(function ($component) {
            return [
                'name' => $component['component']['name'],
                'average' => $component['statistics']['mean'],
                'median' => $component['statistics']['median'],
                'std_dev' => $component['statistics']['standard_deviation'],
            ];
        }, $components);
    }

    /**
     * Format components for difficulty ranking chart.
     */
    private function formatComponentsForDifficultyChart(array $components): array
    {
        usort($components, function ($a, $b) {
            return $b['difficulty_metrics']['difficulty_score'] <=> $a['difficulty_metrics']['difficulty_score'];
        });

        return array_map(function ($component, $rank) {
            return [
                'name' => $component['component']['name'],
                'difficulty_score' => $component['difficulty_metrics']['difficulty_score'],
                'rank' => $rank + 1,
            ];
        }, $components, array_keys($components));
    }

    /**
     * Format components for consistency comparison chart.
     */
    private function formatComponentsForConsistencyChart(array $components): array
    {
        return array_map(function ($component) {
            return [
                'name' => $component['component']['name'],
                'std_dev' => $component['statistics']['standard_deviation'],
                'range' => $component['statistics']['range'],
                'consistency_score' => 100 - $component['statistics']['standard_deviation'], // Higher = more consistent
            ];
        }, $components);
    }

    /**
     * Format hourly submission pattern.
     */
    private function formatHourlyPattern(array $hourlyPattern): array
    {
        return array_map(function ($count, $hour) {
            return [
                'hour' => $hour,
                'hour_label' => sprintf('%02d:00', $hour),
                'count' => $count,
            ];
        }, $hourlyPattern, array_keys($hourlyPattern));
    }

    /**
     * Format daily submission pattern.
     */
    private function formatDailyPattern(array $dailyPattern): array
    {
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return array_map(function ($count, $day) use ($dayNames) {
            return [
                'day' => $day,
                'day_name' => $dayNames[$day],
                'count' => $count,
            ];
        }, $dailyPattern, array_keys($dailyPattern));
    }

    // Empty data structure methods

    /**
     * Get empty performance analytics structure.
     */
    private function getEmptyPerformanceAnalytics(): array
    {
        return [
            'course_info' => [
                'id' => null,
                'name' => 'Unknown Course',
                'semester' => 'Unknown Semester',
                'generated_at' => now()->toISOString(),
            ],
            'grade_distribution' => $this->getEmptyGradeDistribution(),
            'component_comparison' => $this->getEmptyComponentComparison(),
            'performance_trends' => $this->getEmptyPerformanceTrends(),
            'submission_patterns' => $this->getEmptySubmissionPatterns(),
        ];
    }

    /**
     * Get empty grade distribution structure.
     */
    private function getEmptyGradeDistribution(): array
    {
        return [
            'histogram' => [],
            'letter_grade_distribution' => [
                'A' => ['min' => 90, 'max' => 100, 'count' => 0, 'percentage' => 0],
                'B' => ['min' => 80, 'max' => 89.99, 'count' => 0, 'percentage' => 0],
                'C' => ['min' => 70, 'max' => 79.99, 'count' => 0, 'percentage' => 0],
                'D' => ['min' => 60, 'max' => 69.99, 'count' => 0, 'percentage' => 0],
                'F' => ['min' => 0, 'max' => 59.99, 'count' => 0, 'percentage' => 0],
            ],
            'statistics' => [
                'total_scores' => 0,
                'mean' => 0,
                'median' => 0,
                'mode' => null,
                'standard_deviation' => 0,
                'variance' => 0,
                'skewness' => 0,
                'kurtosis' => 0,
                'range' => ['min' => 0, 'max' => 0, 'span' => 0],
                'quartiles' => ['q1' => 0, 'q2' => 0, 'q3' => 0],
                'percentiles' => [],
            ],
            'chart_data' => [
                'histogram_chart' => [],
                'pie_chart' => [],
                'box_plot' => [],
            ],
        ];
    }

    /**
     * Get empty component comparison structure.
     */
    private function getEmptyComponentComparison(): array
    {
        return [
            'components' => [],
            'overall_comparison' => [
                'highest_performing_component' => null,
                'lowest_performing_component' => null,
                'most_consistent_component' => null,
                'least_consistent_component' => null,
                'average_difficulty_ranking' => [],
            ],
            'chart_data' => [
                'performance_comparison' => [],
                'difficulty_ranking' => [],
                'consistency_comparison' => [],
            ],
        ];
    }

    /**
     * Get empty performance trends structure.
     */
    private function getEmptyPerformanceTrends(): array
    {
        return [
            'period' => [
                'start_date' => now()->subDays(30)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'days' => 30,
            ],
            'daily_performance' => [],
            'weekly_performance' => [],
            'component_trends' => [],
            'trend_analysis' => [
                'overall_trend' => 'stable',
                'trend_strength' => 0,
                'significant_changes' => [],
            ],
            'chart_data' => [
                'daily_trend_line' => [],
                'weekly_trend_line' => [],
                'component_trends' => [],
            ],
        ];
    }

    /**
     * Get empty submission patterns structure.
     */
    private function getEmptySubmissionPatterns(): array
    {
        return [
            'timing_patterns' => [
                'hourly_distribution' => [],
                'daily_distribution' => [],
            ],
            'late_submission_analysis' => [
                'statistics' => [
                    'peak_submission_hour' => 0,
                    'peak_submission_day' => 0,
                    'total_submissions' => 0,
                    'late_submissions' => 0,
                    'late_submission_rate' => 0,
                ],
                'late_submissions' => [],
                'average_lateness' => 0,
            ],
            'chart_data' => [
                'hourly_heatmap' => [],
                'daily_bar_chart' => [],
                'lateness_distribution' => [],
            ],
        ];
    }

    /**
     * Calculate overall component comparison metrics.
     */
    private function calculateOverallComponentComparison(array $components): array
    {
        if (empty($components)) {
            return [
                'highest_performing_component' => null,
                'lowest_performing_component' => null,
                'most_consistent_component' => null,
                'least_consistent_component' => null,
                'average_difficulty_ranking' => [],
            ];
        }

        // Find highest and lowest performing components
        $highestPerforming = null;
        $lowestPerforming = null;
        $highestMean = -1;
        $lowestMean = 101;

        // Find most and least consistent components
        $mostConsistent = null;
        $leastConsistent = null;
        $lowestStdDev = 101;
        $highestStdDev = -1;

        foreach ($components as $component) {
            $mean = $component['statistics']['mean'];
            $stdDev = $component['statistics']['standard_deviation'];

            // Performance comparison
            if ($mean > $highestMean) {
                $highestMean = $mean;
                $highestPerforming = $component['component'];
            }
            if ($mean < $lowestMean) {
                $lowestMean = $mean;
                $lowestPerforming = $component['component'];
            }

            // Consistency comparison
            if ($stdDev < $lowestStdDev) {
                $lowestStdDev = $stdDev;
                $mostConsistent = $component['component'];
            }
            if ($stdDev > $highestStdDev) {
                $highestStdDev = $stdDev;
                $leastConsistent = $component['component'];
            }
        }

        // Create difficulty ranking
        $difficultyRanking = array_map(function ($component) {
            return [
                'component' => $component['component'],
                'difficulty_score' => $component['difficulty_metrics']['difficulty_score'],
            ];
        }, $components);

        usort($difficultyRanking, function ($a, $b) {
            return $b['difficulty_score'] <=> $a['difficulty_score'];
        });

        return [
            'highest_performing_component' => $highestPerforming,
            'lowest_performing_component' => $lowestPerforming,
            'most_consistent_component' => $mostConsistent,
            'least_consistent_component' => $leastConsistent,
            'average_difficulty_ranking' => $difficultyRanking,
        ];
    }

    /**
     * Calculate overall trend analysis from daily performance data.
     */
    private function calculateOverallTrendAnalysis(array $dailyPerformance): array
    {
        if (count($dailyPerformance) < 2) {
            return [
                'overall_trend' => 'insufficient_data',
                'trend_strength' => 0,
                'significant_changes' => [],
            ];
        }

        $dates = array_keys($dailyPerformance);
        sort($dates);

        $scores = [];
        foreach ($dates as $date) {
            $scores[] = $dailyPerformance[$date]['average'];
        }

        // Calculate linear regression for trend
        $n = count($scores);
        $sumX = array_sum(range(0, $n - 1));
        $sumY = array_sum($scores);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $i * $scores[$i];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Determine trend direction and strength
        $trendStrength = abs($slope);
        if ($slope > 0.5) {
            $overallTrend = 'improving';
        } elseif ($slope < -0.5) {
            $overallTrend = 'declining';
        } else {
            $overallTrend = 'stable';
        }

        // Find significant changes (day-to-day changes > 5%)
        $significantChanges = [];
        for ($i = 1; $i < count($dates); $i++) {
            $prevScore = $dailyPerformance[$dates[$i - 1]]['average'];
            $currScore = $dailyPerformance[$dates[$i]]['average'];
            $change = $currScore - $prevScore;

            if (abs($change) > 5) {
                $significantChanges[] = [
                    'date' => $dates[$i],
                    'change' => round($change, 2),
                    'direction' => $change > 0 ? 'improvement' : 'decline',
                ];
            }
        }

        return [
            'overall_trend' => $overallTrend,
            'trend_strength' => round($trendStrength, 3),
            'slope' => round($slope, 3),
            'significant_changes' => $significantChanges,
        ];
    }

    /**
     * Format daily trend data for chart visualization.
     */
    private function formatDailyTrendForChart(array $dailyPerformance): array
    {
        $dates = array_keys($dailyPerformance);
        sort($dates);

        return array_map(function ($date) use ($dailyPerformance) {
            return [
                'x' => $date,
                'y' => $dailyPerformance[$date]['average'],
                'count' => $dailyPerformance[$date]['count'],
            ];
        }, $dates);
    }

    /**
     * Format weekly trend data for chart visualization.
     */
    private function formatWeeklyTrendForChart(array $weeklyPerformance): array
    {
        $weeks = array_keys($weeklyPerformance);
        sort($weeks);

        return array_map(function ($week) use ($weeklyPerformance) {
            return [
                'x' => $week,
                'y' => $weeklyPerformance[$week]['average'],
                'count' => $weeklyPerformance[$week]['count'],
            ];
        }, $weeks);
    }

    /**
     * Format component trends for chart visualization.
     */
    private function formatComponentTrendsForChart(array $componentTrends): array
    {
        return array_map(function ($trend) {
            $dates = array_keys($trend['daily_averages']);
            sort($dates);

            $data = array_map(function ($date) use ($trend) {
                return [
                    'x' => $date,
                    'y' => $trend['daily_averages'][$date],
                ];
            }, $dates);

            return [
                'component' => $trend['component'],
                'data' => $data,
            ];
        }, $componentTrends);
    }

    /**
     * Format hourly data for heatmap visualization.
     */
    private function formatHourlyHeatmap(array $hourlyPattern): array
    {
        return array_map(function ($count, $hour) {
            return [
                'hour' => $hour,
                'value' => $count,
                'intensity' => $count > 0 ? $count / max($hourlyPattern) : 0,
            ];
        }, $hourlyPattern, array_keys($hourlyPattern));
    }

    /**
     * Format daily data for bar chart visualization.
     */
    private function formatDailyBarChart(array $dailyPattern): array
    {
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        return array_map(function ($count, $day) use ($dayNames) {
            return [
                'day' => $dayNames[$day],
                'value' => $count,
            ];
        }, $dailyPattern, array_keys($dailyPattern));
    }

    /**
     * Format lateness distribution for visualization.
     */
    private function formatLatenessDistribution(array $lateSubmissions): array
    {
        if (empty($lateSubmissions)) {
            return [];
        }

        // Group by lateness ranges
        $ranges = [
            '0-30 min' => ['min' => 0, 'max' => 30, 'count' => 0],
            '30-60 min' => ['min' => 30, 'max' => 60, 'count' => 0],
            '1-6 hours' => ['min' => 60, 'max' => 360, 'count' => 0],
            '6-24 hours' => ['min' => 360, 'max' => 1440, 'count' => 0],
            '1+ days' => ['min' => 1440, 'max' => PHP_INT_MAX, 'count' => 0],
        ];

        foreach ($lateSubmissions as $submission) {
            $minutes = $submission['minutes_late'];
            foreach ($ranges as &$range) {
                if ($minutes >= $range['min'] && $minutes < $range['max']) {
                    $range['count']++;
                    break;
                }
            }
        }

        return array_map(function ($label, $range) {
            return [
                'range' => $label,
                'count' => $range['count'],
            ];
        }, array_keys($ranges), $ranges);
    }
}
