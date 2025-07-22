<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'academic_records' => $this->formatAcademicRecords($this->resource['academic_records']),
            'semester_summary' => $this->formatSemesterSummary($this->resource['semester_summary']),
            'gpa_history' => $this->formatGPAHistory($this->resource['gpa_history']),
            'credit_progression' => $this->formatCreditProgression($this->resource['credit_progression']),
            'academic_achievements' => $this->formatAchievements($this->resource['academic_achievements']),
            'performance_analytics' => $this->generatePerformanceAnalytics(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format academic records
     */
    protected function formatAcademicRecords(array $records): array
    {
        return collect($records)->map(function ($semesterRecord) {
            return [
                'semester' => $semesterRecord['semester'],
                'courses' => collect($semesterRecord['courses'])->map(function ($course) {
                    return [
                        'unit_code' => $course['unit_code'],
                        'unit_name' => $course['unit_name'],
                        'credit_hours' => $course['credit_hours'],
                        'grade' => [
                            'letter' => $course['grade'],
                            'points' => $course['grade_points'],
                            'display' => $course['grade'],
                            'color' => $this->getGradeColor($course['grade']),
                            'status' => $this->getGradeStatus($course['grade']),
                        ],
                        'completion_status' => $course['completion_status'],
                        'completion_status_display' => $this->getCompletionStatusDisplay($course['completion_status']),
                        'lecturer' => $course['lecturer'],
                        'performance_indicator' => $this->getPerformanceIndicator($course['grade']),
                    ];
                })->toArray(),
                'semester_metrics' => $this->calculateSemesterMetrics($semesterRecord['courses']),
            ];
        })->toArray();
    }

    /**
     * Format semester summary
     */
    protected function formatSemesterSummary(array $summary): array
    {
        return collect($summary)->map(function ($semester) {
            return [
                'semester_name' => $semester['semester_name'],
                'academic_performance' => [
                    'total_courses' => $semester['total_courses'],
                    'completed_courses' => $semester['completed_courses'],
                    'completion_rate' => $semester['total_courses'] > 0 
                        ? round(($semester['completed_courses'] / $semester['total_courses']) * 100, 1)
                        : 0,
                ],
                'credit_summary' => [
                    'total_credits' => $semester['total_credits'],
                    'earned_credits' => $semester['earned_credits'],
                    'credit_efficiency' => $semester['total_credits'] > 0 
                        ? round(($semester['earned_credits'] / $semester['total_credits']) * 100, 1)
                        : 0,
                ],
                'gpa_info' => [
                    'semester_gpa' => $semester['semester_gpa'],
                    'gpa_status' => $this->getGPAStatus($semester['semester_gpa']),
                    'gpa_color' => $this->getGPAColor($semester['semester_gpa']),
                ],
                'performance_level' => $this->getSemesterPerformanceLevel($semester),
            ];
        })->toArray();
    }

    /**
     * Format GPA history
     */
    protected function formatGPAHistory(array $gpaHistory): array
    {
        return [
            'gpa_trend' => collect($gpaHistory)->map(function ($record) {
                return [
                    'semester' => $record['semester'],
                    'gpa' => $record['gpa'],
                    'credit_hours' => $record['credit_hours'],
                    'academic_standing' => $record['academic_standing'],
                    'standing_color' => $this->getStandingColor($record['academic_standing']),
                    'trend_indicator' => $this->getTrendIndicator($record, $gpaHistory),
                ];
            })->toArray(),
            'gpa_analytics' => $this->analyzeGPATrend($gpaHistory),
        ];
    }

    /**
     * Format credit progression
     */
    protected function formatCreditProgression(array $progression): array
    {
        return [
            'progression_data' => collect($progression)->map(function ($record) {
                return [
                    'semester' => $record['semester'],
                    'semester_credits' => $record['semester_credits'],
                    'cumulative_credits' => $record['cumulative_credits'],
                    'progress_percentage' => $this->calculateProgressPercentage($record['cumulative_credits']),
                ];
            })->toArray(),
            'progression_analytics' => $this->analyzeCreditProgression($progression),
        ];
    }

    /**
     * Format achievements
     */
    protected function formatAchievements(array $achievements): array
    {
        return collect($achievements)->map(function ($achievement) {
            return [
                'type' => $achievement['type'],
                'title' => $achievement['title'],
                'description' => $achievement['description'],
                'semester' => $achievement['semester'],
                'date' => $achievement['date'],
                'badge' => $this->getAchievementBadge($achievement['type']),
                'significance' => $this->getAchievementSignificance($achievement['type']),
            ];
        })->toArray();
    }

    /**
     * Generate performance analytics
     */
    protected function generatePerformanceAnalytics(): array
    {
        return [
            'overall_trends' => [
                'gpa_trend' => 'stable', // This would be calculated from actual data
                'credit_accumulation_rate' => 'on_track',
                'performance_consistency' => 'consistent',
            ],
            'strengths' => [
                'Strong performance in core subjects',
                'Consistent attendance and participation',
                'Good time management skills',
            ],
            'areas_for_improvement' => [
                'Consider additional study support for challenging subjects',
                'Explore tutoring options for specific areas',
                'Maintain consistent study schedule',
            ],
            'recommendations' => [
                'Continue current study strategies',
                'Consider advanced courses in strong subject areas',
                'Seek academic support when needed',
            ],
        ];
    }

    /**
     * Calculate semester metrics
     */
    protected function calculateSemesterMetrics(array $courses): array
    {
        $totalCourses = count($courses);
        $completedCourses = collect($courses)->where('completion_status', 'completed')->count();
        $totalCredits = collect($courses)->sum('credit_hours');
        $averageGradePoints = collect($courses)->avg('grade_points');

        return [
            'course_load' => $this->getCourseLoadStatus($totalCourses),
            'completion_rate' => $totalCourses > 0 ? round(($completedCourses / $totalCourses) * 100, 1) : 0,
            'credit_load' => $totalCredits,
            'average_performance' => round($averageGradePoints, 2),
            'performance_level' => $this->getPerformanceLevelFromGPA($averageGradePoints),
        ];
    }

    /**
     * Analyze GPA trend
     */
    protected function analyzeGPATrend(array $gpaHistory): array
    {
        if (count($gpaHistory) < 2) {
            return [
                'trend' => 'insufficient_data',
                'direction' => 'unknown',
                'consistency' => 'unknown',
            ];
        }

        $gpas = collect($gpaHistory)->pluck('gpa');
        $latest = $gpas->last();
        $previous = $gpas->get($gpas->count() - 2);
        $change = $latest - $previous;

        return [
            'trend' => $change > 0.1 ? 'improving' : ($change < -0.1 ? 'declining' : 'stable'),
            'direction' => $change > 0 ? 'upward' : ($change < 0 ? 'downward' : 'stable'),
            'consistency' => $this->calculateGPAConsistency($gpas),
            'highest_gpa' => $gpas->max(),
            'lowest_gpa' => $gpas->min(),
            'average_gpa' => round($gpas->avg(), 2),
        ];
    }

    /**
     * Analyze credit progression
     */
    protected function analyzeCreditProgression(array $progression): array
    {
        $totalCredits = collect($progression)->last()['cumulative_credits'] ?? 0;
        $semesterCount = count($progression);
        $averageCreditsPerSemester = $semesterCount > 0 ? $totalCredits / $semesterCount : 0;

        return [
            'total_credits_earned' => $totalCredits,
            'average_credits_per_semester' => round($averageCreditsPerSemester, 1),
            'progression_rate' => $this->getProgressionRate($averageCreditsPerSemester),
            'estimated_completion' => $this->estimateCompletion($totalCredits, $averageCreditsPerSemester),
        ];
    }

    /**
     * Helper methods
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

    protected function getPerformanceIndicator(string $grade): string
    {
        return match ($grade) {
            'HD' => 'outstanding',
            'D' => 'excellent',
            'C' => 'good',
            'P' => 'satisfactory',
            'N', 'F' => 'needs_improvement',
            default => 'unknown',
        };
    }

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

    protected function getGPAColor(float $gpa): string
    {
        return match (true) {
            $gpa >= 3.5 => '#22c55e', // Green
            $gpa >= 3.0 => '#3b82f6', // Blue
            $gpa >= 2.5 => '#f59e0b', // Amber
            $gpa >= 2.0 => '#f97316', // Orange
            default => '#ef4444',     // Red
        };
    }

    protected function getStandingColor(string $standing): string
    {
        return match ($standing) {
            'Dean\'s List', 'High Honors' => '#22c55e', // Green
            'Good Standing' => '#3b82f6',               // Blue
            'Satisfactory Standing' => '#f59e0b',       // Amber
            'Academic Probation' => '#ef4444',          // Red
            default => '#6b7280',                       // Gray
        };
    }

    protected function getTrendIndicator(array $record, array $allRecords): string
    {
        $currentIndex = array_search($record, $allRecords);
        if ($currentIndex === false || $currentIndex === 0) {
            return 'baseline';
        }

        $previousGPA = $allRecords[$currentIndex - 1]['gpa'];
        $currentGPA = $record['gpa'];
        $change = $currentGPA - $previousGPA;

        return match (true) {
            $change > 0.2 => 'significant_improvement',
            $change > 0.05 => 'improvement',
            $change < -0.2 => 'significant_decline',
            $change < -0.05 => 'decline',
            default => 'stable',
        };
    }

    protected function getSemesterPerformanceLevel(array $semester): string
    {
        $gpa = $semester['semester_gpa'];
        $completionRate = $semester['total_courses'] > 0 
            ? ($semester['completed_courses'] / $semester['total_courses']) * 100
            : 0;

        if ($gpa >= 3.5 && $completionRate >= 90) {
            return 'outstanding';
        } elseif ($gpa >= 3.0 && $completionRate >= 80) {
            return 'excellent';
        } elseif ($gpa >= 2.5 && $completionRate >= 70) {
            return 'good';
        } elseif ($gpa >= 2.0 && $completionRate >= 60) {
            return 'satisfactory';
        } else {
            return 'needs_improvement';
        }
    }

    protected function getCourseLoadStatus(int $courseCount): string
    {
        return match (true) {
            $courseCount >= 6 => 'heavy',
            $courseCount >= 4 => 'full',
            $courseCount >= 2 => 'moderate',
            $courseCount >= 1 => 'light',
            default => 'none',
        };
    }

    protected function getPerformanceLevelFromGPA(float $gpa): string
    {
        return match (true) {
            $gpa >= 3.7 => 'outstanding',
            $gpa >= 3.0 => 'excellent',
            $gpa >= 2.5 => 'good',
            $gpa >= 2.0 => 'satisfactory',
            default => 'needs_improvement',
        };
    }

    protected function calculateGPAConsistency(Collection $gpas): string
    {
        if ($gpas->count() < 3) {
            return 'insufficient_data';
        }

        $mean = $gpas->avg();
        $variance = $gpas->map(fn($gpa) => pow($gpa - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        return match (true) {
            $stdDev < 0.2 => 'very_consistent',
            $stdDev < 0.4 => 'consistent',
            $stdDev < 0.6 => 'moderate',
            default => 'inconsistent',
        };
    }

    protected function calculateProgressPercentage(int $cumulativeCredits): float
    {
        $totalCreditsRequired = 144; // Typical bachelor's degree
        return $totalCreditsRequired > 0 ? round(($cumulativeCredits / $totalCreditsRequired) * 100, 1) : 0;
    }

    protected function getProgressionRate(float $averageCreditsPerSemester): string
    {
        return match (true) {
            $averageCreditsPerSemester >= 20 => 'accelerated',
            $averageCreditsPerSemester >= 15 => 'on_track',
            $averageCreditsPerSemester >= 12 => 'moderate',
            default => 'slow',
        };
    }

    protected function estimateCompletion(int $totalCredits, float $averageCreditsPerSemester): array
    {
        $creditsRemaining = max(0, 144 - $totalCredits); // Assuming 144 total credits needed
        $semestersRemaining = $averageCreditsPerSemester > 0 
            ? ceil($creditsRemaining / $averageCreditsPerSemester)
            : 0;

        return [
            'credits_remaining' => $creditsRemaining,
            'semesters_remaining' => $semestersRemaining,
            'estimated_graduation_date' => $semestersRemaining > 0 
                ? now()->addMonths($semestersRemaining * 6)->format('Y-m-d')
                : null,
        ];
    }

    protected function getAchievementBadge(string $type): array
    {
        return match ($type) {
            'deans_list' => [
                'icon' => 'star',
                'color' => '#fbbf24',
                'background' => '#fef3c7',
            ],
            'honor_roll' => [
                'icon' => 'trophy',
                'color' => '#f59e0b',
                'background' => '#fef3c7',
            ],
            'perfect_attendance' => [
                'icon' => 'check-circle',
                'color' => '#22c55e',
                'background' => '#dcfce7',
            ],
            default => [
                'icon' => 'academic-cap',
                'color' => '#3b82f6',
                'background' => '#dbeafe',
            ],
        };
    }

    protected function getAchievementSignificance(string $type): string
    {
        return match ($type) {
            'deans_list' => 'high',
            'honor_roll' => 'medium',
            'perfect_attendance' => 'medium',
            default => 'low',
        };
    }
}
