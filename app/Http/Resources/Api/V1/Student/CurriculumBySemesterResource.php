<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumBySemesterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'semesters' => $this->formatSemesters($this->resource['semesters']),
            'overall_summary' => $this->resource['overall_summary'],
        ];
    }

    /**
     * Format semesters data
     */
    protected function formatSemesters(array $semesters): array
    {
        return collect($semesters)->map(function ($semester) {
            return [
                'semester_info' => [
                    'year_level' => $semester['semester_info']['year_level'],
                    'semester_number' => $semester['semester_info']['semester_number'],
                    'display_name' => $semester['semester_info']['display_name'],
                ],
                'subjects' => $this->formatSubjects($semester['subjects']),
                'summary' => $semester['summary'],
            ];
        })->toArray();
    }

    /**
     * Format subjects within a semester
     */
    protected function formatSubjects(array $subjects): array
    {
        return collect($subjects)->map(function ($subject) {
            return [
                'curriculum_unit' => [
                    'id' => $subject['curriculum_unit']['id'],
                    'year_level' => $subject['curriculum_unit']['year_level'],
                    'semester_number' => $subject['curriculum_unit']['semester_number'],
                    'unit_scope' => $subject['curriculum_unit']['unit_scope'],
                    'note' => $subject['curriculum_unit']['note'],
                ],
                'unit' => [
                    'id' => $subject['unit']['id'],
                    'code' => $subject['unit']['code'],
                    'name' => $subject['unit']['name'],
                    'credit_points' => $subject['unit']['credit_points'],
                ],
                'study_status' => $subject['study_status'],
                'total_grade' => $this->formatTotalGrade($subject['grade_info']),
                'registration_info' => [
                    'has_registration' => $subject['has_registration'],
                    'registrations' => $this->formatRegistrations($subject['registrations']),
                ],
            ];
        })->toArray();
    }

    /**
     * Format total grade information from academic_record
     */
    protected function formatTotalGrade(?array $gradeInfo): ?array
    {
        if (!$gradeInfo) {
            return null;
        }

        return [
            'final_percentage' => $gradeInfo['final_percentage'],
            'final_letter_grade' => $gradeInfo['final_letter_grade'],
            'grade_points' => $gradeInfo['grade_points'],
            'grade_status' => $gradeInfo['grade_status'],
            'completion_status' => $gradeInfo['completion_status'] ?? null,
        ];
    }

    /**
     * Format registrations data
     */
    protected function formatRegistrations(array $registrations): array
    {
        return collect($registrations)->map(function ($registration) {
            return [
                'id' => $registration['id'],
                'status' => $registration['status'],
                'registration_date' => $registration['registration_date'],
                'semester' => [
                    'id' => $registration['semester']['id'],
                    'name' => $registration['semester']['name'],
                    'code' => $registration['semester']['code'],
                ],
            ];
        })->toArray();
    }

    /**
     * Format semester summary
     */
    protected function formatSemesterSummary(array $summary): array
    {
        return [
            'totals' => [
                'total_subjects' => $summary['total_subjects'],
                'total_credit_points' => $summary['total_credit_points'],
                'completed_subjects' => $summary['completed_subjects'],
                'current_subjects' => $summary['current_subjects'],
                'not_started_subjects' => $summary['not_started_subjects'],
                'registered_subjects' => $summary['registered_subjects'],
            ],
            'percentages' => [
                'completion_percentage' => $summary['total_subjects'] > 0
                    ? round(($summary['completed_subjects'] / $summary['total_subjects']) * 100, 1)
                    : 0,
                'registration_percentage' => $summary['total_subjects'] > 0
                    ? round(($summary['registered_subjects'] / $summary['total_subjects']) * 100, 1)
                    : 0,
                'progress_percentage' => $summary['total_subjects'] > 0
                    ? round((($summary['completed_subjects'] + $summary['current_subjects']) / $summary['total_subjects']) * 100, 1)
                    : 0,
            ],
            'semester_status' => $this->getSemesterStatus($summary),
        ];
    }

    /**
     * Format overall summary
     */
    protected function formatOverallSummary(array $summary): array
    {
        return [
            'curriculum_totals' => [
                'total_semesters' => $summary['total_semesters'],
                'total_subjects' => $summary['total_subjects'],
                'total_credit_points' => $summary['total_credit_points'],
            ],
            'progress_totals' => [
                'completed_subjects' => $summary['completed_subjects'],
                'current_subjects' => $summary['current_subjects'],
                'not_started_subjects' => $summary['not_started_subjects'],
                'registered_subjects' => $summary['registered_subjects'],
            ],
            'completion_metrics' => [
                'overall_completion_percentage' => $summary['total_subjects'] > 0
                    ? round(($summary['completed_subjects'] / $summary['total_subjects']) * 100, 1)
                    : 0,
                'registration_coverage' => $summary['total_subjects'] > 0
                    ? round(($summary['registered_subjects'] / $summary['total_subjects']) * 100, 1)
                    : 0,
                'active_participation' => $summary['total_subjects'] > 0
                    ? round((($summary['completed_subjects'] + $summary['current_subjects']) / $summary['total_subjects']) * 100, 1)
                    : 0,
            ],
            'curriculum_health' => $this->generateCurriculumHealth($summary),
        ];
    }

    /**
     * Generate study progress indicators
     */
    protected function generateStudyProgressIndicators(): array
    {
        $summary = $this->resource['overall_summary'];
        $completionPercentage = $summary['total_subjects'] > 0
            ? ($summary['completed_subjects'] / $summary['total_subjects']) * 100
            : 0;

        return [
            'overall_progress' => [
                'percentage' => round($completionPercentage, 1),
                'status' => $this->getOverallProgressStatus($completionPercentage),
                'color' => $this->getProgressColor($completionPercentage),
                'milestone' => $this->getProgressMilestone($completionPercentage),
            ],
            'study_momentum' => $this->calculateStudyMomentum(),
            'completion_forecast' => $this->generateCompletionForecast(),
        ];
    }

    /**
     * Generate registration insights
     */
    protected function generateRegistrationInsights(): array
    {
        $registrationRate = $this->calculateRegistrationRate();

        return [
            'registration_analytics' => [
                'current_registration_rate' => $registrationRate,
                'registration_trend' => $this->getRegistrationTrend($registrationRate),
                'engagement_level' => $this->getEngagementLevel($registrationRate),
            ],
            'recommendations' => $this->generateRecommendations(),
        ];
    }

    // Helper methods for data transformation

    protected function getUnitScopeDisplay(string $scope): string
    {
        if ($scope === null) {
            return 'Unspecified';
        }

        return match ($scope) {
            'common' => 'Core Subject',
            'specialization_specific' => 'Specialization Subject',
            'cross_program' => 'Elective Subject',
            default => ucfirst(str_replace('_', ' ', $scope)),
        };
    }

    protected function getStudyStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'green',
            'in_progress' => 'blue',
            'registered' => 'purple',
            'failed' => 'red',
            'not_started' => 'gray',
            default => 'gray',
        };
    }

    protected function getStudyStatusIcon(string $status): string
    {
        return match ($status) {
            'completed' => 'check-circle',
            'in_progress' => 'clock',
            'registered' => 'bookmark',
            'failed' => 'x-circle',
            'not_started' => 'circle',
            default => 'circle',
        };
    }

    protected function getStudyStatusPriority(string $status): int
    {
        return match ($status) {
            'completed' => 4,
            'in_progress' => 3,
            'registered' => 2,
            'failed' => 1,
            'not_started' => 0,
            default => 0,
        };
    }

    protected function formatGradeDisplay(?array $gradeInfo): ?string
    {
        if (!$gradeInfo) {
            return null;
        }

        if ($gradeInfo['final_letter_grade']) {
            return $gradeInfo['final_letter_grade'] .
                ($gradeInfo['final_percentage'] ? " ({$gradeInfo['final_percentage']}%)" : '');
        }

        if ($gradeInfo['final_percentage']) {
            return "{$gradeInfo['final_percentage']}%";
        }

        return null;
    }

    protected function getPerformanceIndicator(?array $gradeInfo): ?array
    {
        if (!$gradeInfo || !$gradeInfo['grade_points']) {
            return null;
        }

        $gpa = $gradeInfo['grade_points'];

        return [
            'performance_level' => match (true) {
                $gpa >= 3.5 => 'excellent',
                $gpa >= 3.0 => 'good',
                $gpa >= 2.5 => 'satisfactory',
                $gpa >= 2.0 => 'needs_improvement',
                default => 'poor',
            },
            'color' => match (true) {
                $gpa >= 3.5 => 'green',
                $gpa >= 3.0 => 'blue',
                $gpa >= 2.5 => 'yellow',
                $gpa >= 2.0 => 'orange',
                default => 'red',
            },
        ];
    }

    protected function getRegistrationStatusDisplay(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'registered' => 'Registered',
            'confirmed' => 'Confirmed',
            'dropped' => 'Dropped',
            'withdrawn' => 'Withdrawn',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => ucfirst($status),
        };
    }

    protected function getRegistrationStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'yellow',
            'registered' => 'blue',
            'confirmed' => 'green',
            'dropped' => 'gray',
            'withdrawn' => 'orange',
            'completed' => 'green',
            'failed' => 'red',
            default => 'gray',
        };
    }

    protected function getRegistrationStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'clock',
            'registered' => 'bookmark',
            'confirmed' => 'check-circle',
            'dropped' => 'x-circle',
            'withdrawn' => 'minus-circle',
            'completed' => 'check-circle',
            'failed' => 'x-circle',
            default => 'circle',
        };
    }

    protected function getLatestRegistrationStatus(array $registrations): ?string
    {
        if (empty($registrations)) {
            return null;
        }

        // Return the status of the most recent registration
        $latest = collect($registrations)->sortByDesc('registration_date')->first();
        return $latest['status'] ?? null;
    }

    protected function generateAcademicIndicators(array $subject): array
    {
        return [
            'priority_indicator' => $subject['has_registration'] ? 'high' : 'normal',
            'academic_weight' => $subject['unit']['credit_points'],
            'progress_contribution' => $this->calculateProgressContribution($subject),
        ];
    }

    protected function calculateProgressContribution(array $subject): array
    {
        $creditPoints = $subject['unit']['credit_points'] ?? 0;

        return [
            'credit_contribution' => $creditPoints,
            'completion_impact' => match ($subject['study_status']['status']) {
                'completed' => $creditPoints,
                'in_progress' => $creditPoints * 0.5, // Partial credit for current subjects
                default => 0,
            },
        ];
    }

    protected function generateSemesterProgressIndicators(array $summary): array
    {
        $completionRate = $summary['total_subjects'] > 0
            ? ($summary['completed_subjects'] / $summary['total_subjects']) * 100
            : 0;

        return [
            'completion_rate' => round($completionRate, 1),
            'engagement_score' => $this->calculateEngagementScore($summary),
            'semester_health' => $this->getSemesterHealthStatus($summary),
        ];
    }

    protected function calculateEngagementScore(array $summary): int
    {
        if ($summary['total_subjects'] === 0) {
            return 0;
        }

        $activeSubjects = $summary['completed_subjects'] + $summary['current_subjects'] + $summary['registered_subjects'];
        $engagementRate = ($activeSubjects / $summary['total_subjects']) * 100;

        return min(100, max(0, (int) round($engagementRate)));
    }

    protected function getSemesterStatus(array $summary): string
    {
        $completionRate = $summary['total_subjects'] > 0
            ? ($summary['completed_subjects'] / $summary['total_subjects']) * 100
            : 0;

        return match (true) {
            $completionRate >= 100 => 'completed',
            $completionRate >= 50 => 'in_progress',
            $summary['registered_subjects'] > 0 => 'active',
            default => 'planned',
        };
    }

    protected function getSemesterHealthStatus(array $summary): string
    {
        $engagementScore = $this->calculateEngagementScore($summary);

        return match (true) {
            $engagementScore >= 80 => 'excellent',
            $engagementScore >= 60 => 'good',
            $engagementScore >= 40 => 'fair',
            $engagementScore >= 20 => 'needs_attention',
            default => 'critical',
        };
    }

    protected function formatAcademicPeriod(array $semesterInfo): string
    {
        return "Year {$semesterInfo['year_level']}, Semester {$semesterInfo['semester_number']}";
    }

    protected function generateCurriculumHealth(array $summary): array
    {
        $completionRate = $summary['total_subjects'] > 0
            ? ($summary['completed_subjects'] / $summary['total_subjects']) * 100
            : 0;

        return [
            'health_score' => $this->calculateHealthScore($summary),
            'health_status' => $this->getHealthStatus($completionRate),
            'improvement_areas' => $this->identifyImprovementAreas($summary),
        ];
    }

    protected function calculateHealthScore(array $summary): int
    {
        if ($summary['total_subjects'] === 0) {
            return 0;
        }

        $completionWeight = 0.5;
        $progressWeight = 0.3;
        $registrationWeight = 0.2;

        $completionScore = ($summary['completed_subjects'] / $summary['total_subjects']) * 100 * $completionWeight;
        $progressScore = (($summary['completed_subjects'] + $summary['current_subjects']) / $summary['total_subjects']) * 100 * $progressWeight;
        $registrationScore = ($summary['registered_subjects'] / $summary['total_subjects']) * 100 * $registrationWeight;

        return min(100, max(0, (int) round($completionScore + $progressScore + $registrationScore)));
    }

    protected function getHealthStatus(float $completionRate): string
    {
        return match (true) {
            $completionRate >= 80 => 'excellent',
            $completionRate >= 60 => 'good',
            $completionRate >= 40 => 'fair',
            $completionRate >= 20 => 'needs_improvement',
            default => 'critical',
        };
    }

    protected function getOverallProgressStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'nearing_completion',
            $percentage >= 75 => 'advanced',
            $percentage >= 50 => 'intermediate',
            $percentage >= 25 => 'beginning',
            default => 'just_started',
        };
    }

    protected function getProgressColor(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'green',
            $percentage >= 60 => 'blue',
            $percentage >= 40 => 'yellow',
            $percentage >= 20 => 'orange',
            default => 'red',
        };
    }

    protected function getProgressMilestone(float $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'graduation_ready',
            $percentage >= 75 => 'senior_level',
            $percentage >= 50 => 'mid_program',
            $percentage >= 25 => 'foundation_complete',
            default => 'getting_started',
        };
    }

    protected function calculateStudyMomentum(): array
    {
        // This would ideally compare recent progress with historical data
        return [
            'momentum_score' => 75, // Placeholder
            'trend' => 'positive',
            'velocity' => 'steady',
        ];
    }

    protected function generateCompletionForecast(): array
    {
        // This would calculate estimated completion based on current progress
        return [
            'estimated_completion_date' => null, // Would be calculated
            'projected_graduation_semester' => null,
            'acceleration_opportunities' => [],
        ];
    }

    protected function calculateRegistrationRate(): float
    {
        $summary = $this->resource['overall_summary'];
        return $summary['total_subjects'] > 0
            ? ($summary['registered_subjects'] / $summary['total_subjects']) * 100
            : 0;
    }

    protected function getRegistrationTrend(float $rate): string
    {
        return match (true) {
            $rate >= 80 => 'highly_engaged',
            $rate >= 60 => 'well_engaged',
            $rate >= 40 => 'moderately_engaged',
            $rate >= 20 => 'low_engagement',
            default => 'minimal_engagement',
        };
    }

    protected function getEngagementLevel(float $rate): string
    {
        return match (true) {
            $rate >= 75 => 'high',
            $rate >= 50 => 'medium',
            $rate >= 25 => 'low',
            default => 'very_low',
        };
    }

    protected function generateRecommendations(): array
    {
        // This would generate personalized recommendations based on student progress
        return [
            'priority_actions' => [],
            'course_suggestions' => [],
            'timeline_adjustments' => [],
        ];
    }

    protected function identifyImprovementAreas(array $summary): array
    {
        $areas = [];

        $registrationRate = $summary['total_subjects'] > 0
            ? ($summary['registered_subjects'] / $summary['total_subjects']) * 100
            : 0;

        if ($registrationRate < 50) {
            $areas[] = 'course_registration';
        }

        if ($summary['not_started_subjects'] > $summary['completed_subjects']) {
            $areas[] = 'academic_progress';
        }

        return $areas;
    }
}
