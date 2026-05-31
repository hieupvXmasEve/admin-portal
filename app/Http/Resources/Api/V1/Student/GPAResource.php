<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GPAResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'current_gpa' => $this->formatCurrentGPA($this->resource['current_gpa'] ?? null),
            'gpa_trend' => $this->formatGPATrend($this->resource['gpa_trend'] ?? null),
            'grade_distribution' => $this->formatGradeDistribution($this->resource['grade_distribution'] ?? null),
            'academic_standing' => $this->formatAcademicStanding($this->resource['academic_standing'] ?? null),
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format current GPA data
     */
    protected function formatCurrentGPA(?array $gpaData): ?array
    {
        if (! $gpaData) {
            return null;
        }

        return [
            'gpa' => $gpaData['gpa'],
            'quality_points' => $gpaData['quality_points'],
            'credit_hours_attempted' => $gpaData['credit_hours_attempted'],
            'credit_hours_earned' => $gpaData['credit_hours_earned'],
            'total_courses' => $gpaData['total_courses'],
            'gpa_scale' => '100',
            'performance_level' => $this->getPerformanceLevel($gpaData['gpa']),
        ];
    }

    /**
     * Format GPA trend data
     */
    protected function formatGPATrend(?array $trendData): ?array
    {
        if (! $trendData) {
            return null;
        }

        return [
            'trend_data' => $trendData['trend_data'],
            'trend_analysis' => $trendData['trend_analysis'],
            'chart_data' => $this->formatChartData($trendData['trend_data']),
        ];
    }

    /**
     * Format grade distribution data
     */
    protected function formatGradeDistribution(?array $distributionData): ?array
    {
        if (! $distributionData) {
            return null;
        }

        return [
            'distribution' => $distributionData['distribution'],
            'percentages' => $distributionData['percentages'],
            'total_courses' => $distributionData['total_courses'],
            'chart_data' => $this->formatDistributionChartData($distributionData),
        ];
    }

    /**
     * Format academic standing data
     */
    protected function formatAcademicStanding(?array $standingData): ?array
    {
        if (! $standingData) {
            return null;
        }

        return [
            'standing' => $standingData['standing'],
            'standing_description' => $this->getStandingDescription($standingData['standing']),
            'gpa' => $standingData['gpa'],
            'semester_gpa' => $standingData['semester_gpa'] ?? null,
            'cumulative_gpa' => $standingData['cumulative_gpa'] ?? $standingData['gpa'],
            'required_gpa' => $standingData['required_gpa'],
            'meets_requirement' => $standingData['meets_requirement'],
            'warning_level' => $standingData['warning_level'],
            'dean_list_eligible' => $standingData['dean_list_eligible'] ?? false,
            'honors_eligible' => $standingData['honors_eligible'] ?? false,
        ];
    }

    /**
     * Get performance level description
     */
    protected function getPerformanceLevel(float $gpa): string
    {
        return match (true) {
            $gpa >= 85 => 'excellent',
            $gpa >= 75 => 'very_good',
            $gpa >= 65 => 'good',
            $gpa >= 50 => 'normal',
            default => 'unsatisfactory',
        };
    }

    /**
     * Get standing description
     */
    protected function getStandingDescription(string $standing): string
    {
        return match ($standing) {
            'normal' => 'Normal',
            'warning' => 'Warning',
            default => 'Unknown Standing',
        };
    }

    /**
     * Format chart data for trend visualization
     */
    protected function formatChartData(array $trendData): array
    {
        return collect($trendData)->map(function ($item) {
            return [
                'label' => $item['semester'],
                'value' => $item['gpa'],
                'credits' => $item['credit_hours'],
            ];
        })->toArray();
    }

    /**
     * Format chart data for grade distribution
     */
    protected function formatDistributionChartData(array $distributionData): array
    {
        $chartData = [];

        foreach ($distributionData['distribution'] as $grade => $count) {
            $chartData[] = [
                'label' => $grade,
                'value' => $count,
                'percentage' => $distributionData['percentages'][$grade] ?? 0,
                'color' => $this->getGradeColor($grade),
            ];
        }

        return $chartData;
    }

    /**
     * Get color for grade visualization
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
}
