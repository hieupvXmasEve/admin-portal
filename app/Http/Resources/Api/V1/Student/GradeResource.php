<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'grades_by_semester' => $this->formatGradesBySemester($this->resource['grades_by_semester']),
            'overall_summary' => $this->formatOverallSummary($this->resource['overall_summary']),
            'grade_distribution' => $this->formatGradeDistribution($this->resource['grade_distribution']),
            'performance_trends' => $this->formatPerformanceTrends($this->resource['performance_trends']),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format grades by semester
     */
    protected function formatGradesBySemester(array $gradesBySemester): array
    {
        return collect($gradesBySemester)->map(function ($semesterData) {
            return [
                'semester' => $semesterData['semester'],
                'courses' => collect($semesterData['courses'])->map(function ($course) {
                    return [
                        'id' => $course['id'],
                        'unit_code' => $course['unit_code'],
                        'unit_name' => $course['unit_name'],
                        'credit_hours' => $course['credit_hours'],
                        'grade' => [
                            'letter' => $course['final_grade'],
                            'numeric' => $course['numeric_grade'],
                            'points' => $course['grade_points'],
                            'display' => $this->formatGradeDisplay($course['final_grade'], $course['numeric_grade']),
                            'status' => $this->getGradeStatus($course['final_grade']),
                        ],
                        'completion' => [
                            'status' => $course['completion_status'],
                            'status_display' => $this->getCompletionStatusDisplay($course['completion_status']),
                            'date' => $course['completion_date'],
                        ],
                        'lecturer' => $course['lecturer'],
                    ];
                })->toArray(),
                'semester_summary' => [
                    'statistics' => $semesterData['semester_summary'],
                    'performance_indicators' => $this->calculatePerformanceIndicators($semesterData['semester_summary']),
                ],
            ];
        })->toArray();
    }

    /**
     * Format overall summary
     */
    protected function formatOverallSummary(array $summary): array
    {
        return [
            'academic_progress' => [
                'total_courses_attempted' => $summary['total_courses_attempted'],
                'total_courses_completed' => $summary['total_courses_completed'],
                'completion_rate' => $summary['completion_rate'],
                'completion_status' => $this->getCompletionStatus($summary['completion_rate']),
            ],
            'credit_summary' => [
                'total_credits_attempted' => $summary['total_credits_attempted'],
                'total_credits_earned' => $summary['total_credits_earned'],
                'credit_efficiency' => $summary['total_credits_attempted'] > 0
                    ? round(($summary['total_credits_earned'] / $summary['total_credits_attempted']) * 100, 1)
                    : 0,
            ],
            'gpa_summary' => [
                'overall_gpa' => $summary['overall_gpa'],
                'gpa_status' => $this->getGPAStatus($summary['overall_gpa']),
                'academic_standing' => $this->getAcademicStanding($summary['overall_gpa']),
            ],
        ];
    }

    /**
     * Format grade distribution
     */
    protected function formatGradeDistribution(array $distribution): array
    {
        return [
            'distribution' => collect($distribution)->map(function ($gradeData) {
                return [
                    'grade' => $gradeData['grade'],
                    'grade_display' => $this->getGradeDescription($gradeData['grade']),
                    'count' => $gradeData['count'],
                    'percentage' => $gradeData['percentage'],
                    'color' => $this->getGradeColor($gradeData['grade']),
                ];
            })->toArray(),
            'analysis' => $this->analyzeGradeDistribution($distribution),
        ];
    }

    /**
     * Format performance trends
     */
    protected function formatPerformanceTrends(array $trends): array
    {
        return [
            'trend_summary' => [
                'overall_trend' => $trends['trend'],
                'direction' => $trends['direction'],
                'improvement_rate' => $trends['improvement_rate'],
                'consistency' => $trends['consistency'],
            ],
            'trend_analysis' => $this->getTrendAnalysis($trends),
            'recommendations' => $this->getTrendRecommendations($trends),
        ];
    }

    /**
     * Format grade display
     */
    protected function formatGradeDisplay(string $letterGrade, ?float $numericGrade): string
    {
        if ($numericGrade !== null) {
            return "{$letterGrade} ({$numericGrade}%)";
        }

        return $letterGrade;
    }

    /**
     * Get grade status
     */
    protected function getGradeStatus(string $grade): string
    {
        return match ($grade) {
            'HD', 'D' => 'excellent',
            'C' => 'good',
            'P' => 'satisfactory',
            'N', 'F' => 'unsatisfactory',
            default => 'unknown',
        };
    }

    /**
     * Get completion status display
     */
    protected function getCompletionStatusDisplay(string $status): string
    {
        return match ($status) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            'failed' => 'Failed',
            'withdrawn' => 'Withdrawn',
            default => ucfirst($status),
        };
    }

    /**
     * Calculate performance indicators
     */
    protected function calculatePerformanceIndicators(array $summary): array
    {
        return [
            'semester_performance' => $this->getSemesterPerformance($summary['semester_gpa']),
            'credit_load' => $this->getCreditLoadStatus($summary['total_credits']),
            'success_rate' => $summary['total_courses'] > 0
                ? round(($summary['completed_courses'] / $summary['total_courses']) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get completion status
     */
    protected function getCompletionStatus(float $rate): string
    {
        return match (true) {
            $rate >= 95 => 'excellent',
            $rate >= 85 => 'good',
            $rate >= 75 => 'satisfactory',
            $rate >= 60 => 'needs_improvement',
            default => 'concerning',
        };
    }

    /**
     * Get GPA status
     */
    protected function getGPAStatus(float $gpa): string
    {
        return match (true) {
            $gpa >= 3.5 => 'excellent',
            $gpa >= 3.0 => 'good',
            $gpa >= 2.5 => 'satisfactory',
            $gpa >= 2.0 => 'needs_improvement',
            default => 'unsatisfactory',
        };
    }

    /**
     * Get academic standing
     */
    protected function getAcademicStanding(float $gpa): string
    {
        return match (true) {
            $gpa >= 3.7 => 'Dean\'s List',
            $gpa >= 3.5 => 'High Honors',
            $gpa >= 3.0 => 'Good Standing',
            $gpa >= 2.0 => 'Satisfactory Standing',
            default => 'Academic Probation',
        };
    }

    /**
     * Get grade description
     */
    protected function getGradeDescription(string $grade): string
    {
        return match ($grade) {
            'HD' => 'High Distinction',
            'D' => 'Distinction',
            'C' => 'Credit',
            'P' => 'Pass',
            'N' => 'Fail (No Pass)',
            'F' => 'Fail',
            default => $grade,
        };
    }

    /**
     * Get grade color for visualization
     */
    protected function getGradeColor(string $grade): string
    {
        return match ($grade) {
            'HD' => '#22c55e', // Green
            'D' => '#3b82f6',  // Blue
            'C' => '#f59e0b',  // Amber
            'P' => '#f97316',  // Orange
            'N', 'F' => '#ef4444', // Red
            default => '#6b7280', // Gray
        };
    }

    /**
     * Analyze grade distribution
     */
    protected function analyzeGradeDistribution(array $distribution): array
    {
        $totalGrades = array_sum(array_column($distribution, 'count'));
        $highGrades = 0;
        $passingGrades = 0;

        foreach ($distribution as $gradeData) {
            if (in_array($gradeData['grade'], ['HD', 'D'])) {
                $highGrades += $gradeData['count'];
            }
            if (in_array($gradeData['grade'], ['HD', 'D', 'C', 'P'])) {
                $passingGrades += $gradeData['count'];
            }
        }

        return [
            'high_performance_rate' => $totalGrades > 0 ? round(($highGrades / $totalGrades) * 100, 1) : 0,
            'pass_rate' => $totalGrades > 0 ? round(($passingGrades / $totalGrades) * 100, 1) : 0,
            'dominant_grade' => $this->getDominantGrade($distribution),
            'performance_pattern' => $this->getPerformancePattern($distribution),
        ];
    }

    /**
     * Get trend analysis
     */
    protected function getTrendAnalysis(array $trends): string
    {
        $trend = $trends['trend'];
        $consistency = $trends['consistency'];

        if ($trend === 'improving' && $consistency === 'consistent') {
            return 'Strong upward trajectory with consistent performance';
        } elseif ($trend === 'improving') {
            return 'Performance is improving but with some variability';
        } elseif ($trend === 'declining' && $consistency === 'consistent') {
            return 'Consistent decline in performance - intervention needed';
        } elseif ($trend === 'declining') {
            return 'Performance showing decline with variability';
        } elseif ($consistency === 'consistent') {
            return 'Stable and consistent academic performance';
        } else {
            return 'Variable performance with no clear trend';
        }
    }

    /**
     * Get trend recommendations
     */
    protected function getTrendRecommendations(array $trends): array
    {
        $recommendations = [];

        switch ($trends['trend']) {
            case 'improving':
                $recommendations[] = 'Continue current study strategies';
                $recommendations[] = 'Consider taking on additional challenges';
                break;
            case 'declining':
                $recommendations[] = 'Review study methods and time management';
                $recommendations[] = 'Consider seeking academic support';
                $recommendations[] = 'Meet with academic advisor';
                break;
            case 'stable':
                if ($trends['consistency'] === 'inconsistent') {
                    $recommendations[] = 'Focus on developing consistent study habits';
                } else {
                    $recommendations[] = 'Maintain current performance level';
                }
                break;
        }

        return $recommendations;
    }

    /**
     * Get semester performance level
     */
    protected function getSemesterPerformance(float $gpa): string
    {
        return match (true) {
            $gpa >= 3.5 => 'outstanding',
            $gpa >= 3.0 => 'strong',
            $gpa >= 2.5 => 'adequate',
            $gpa >= 2.0 => 'concerning',
            default => 'critical',
        };
    }

    /**
     * Get credit load status
     */
    protected function getCreditLoadStatus(int $credits): string
    {
        return match (true) {
            $credits >= 18 => 'heavy',
            $credits >= 15 => 'full',
            $credits >= 12 => 'standard',
            $credits >= 9 => 'light',
            default => 'minimal',
        };
    }

    /**
     * Get dominant grade from distribution
     */
    protected function getDominantGrade(array $distribution): string
    {
        $maxCount = 0;
        $dominantGrade = '';

        foreach ($distribution as $gradeData) {
            if ($gradeData['count'] > $maxCount) {
                $maxCount = $gradeData['count'];
                $dominantGrade = $gradeData['grade'];
            }
        }

        return $dominantGrade;
    }

    /**
     * Get performance pattern
     */
    protected function getPerformancePattern(array $distribution): string
    {
        $highGrades = 0;
        $lowGrades = 0;
        $total = array_sum(array_column($distribution, 'count'));

        foreach ($distribution as $gradeData) {
            if (in_array($gradeData['grade'], ['HD', 'D'])) {
                $highGrades += $gradeData['count'];
            } elseif (in_array($gradeData['grade'], ['N', 'F'])) {
                $lowGrades += $gradeData['count'];
            }
        }

        $highPercentage = $total > 0 ? ($highGrades / $total) * 100 : 0;
        $lowPercentage = $total > 0 ? ($lowGrades / $total) * 100 : 0;

        if ($highPercentage >= 60) {
            return 'high_achiever';
        } elseif ($lowPercentage >= 30) {
            return 'struggling';
        } elseif ($highPercentage >= 30 && $lowPercentage <= 10) {
            return 'consistent_performer';
        } else {
            return 'mixed_performance';
        }
    }
}
