<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'curriculum_info' => $this->formatCurriculumInfo($this->resource['curriculum_info']),
            'curriculum_structure' => $this->formatCurriculumStructure($this->resource['curriculum_structure']),
            'progress_summary' => $this->formatProgressSummary($this->resource['progress_summary']),
            'completion_status' => $this->formatCompletionStatus($this->resource['completion_status']),
            'visual_indicators' => $this->generateVisualIndicators(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format curriculum information
     */
    protected function formatCurriculumInfo(array $curriculumInfo): array
    {
        return [
            'curriculum_details' => [
                'id' => $curriculumInfo['id'],
                'version' => $curriculumInfo['version'],
                'effective_date' => $curriculumInfo['effective_date'],
                'total_credit_hours' => $curriculumInfo['total_credit_hours'],
                'display_name' => 'Version '.$curriculumInfo['version'],
            ],
            'program_details' => [
                'id' => $curriculumInfo['program']['id'],
                'name' => $curriculumInfo['program']['name'],
                'code' => $curriculumInfo['program']['code'],
                'degree_type' => $curriculumInfo['program']['degree_type'],
                'degree_type_display' => $this->getDegreeTypeDisplay($curriculumInfo['program']['degree_type']),
                'full_title' => $curriculumInfo['program']['code'].' - '.$curriculumInfo['program']['name'],
            ],
            'curriculum_metadata' => [
                'is_current_version' => true, // This would be determined by comparing with latest version
                'version_status' => 'active',
                'last_updated' => $curriculumInfo['effective_date'],
            ],
        ];
    }

    /**
     * Format curriculum structure
     */
    protected function formatCurriculumStructure(array $structure): array
    {
        return [
            'organization' => [
                'by_category' => $this->formatCategoryBreakdown($structure['by_category']),
                'by_year_level' => $this->formatYearLevelBreakdown($structure['by_year_level']),
                'by_semester' => $this->formatSemesterBreakdown($structure['by_semester']),
            ],
            'totals' => [
                'total_units' => $structure['total_units'],
                'total_credit_hours' => $structure['total_credit_hours'],
                'average_credits_per_unit' => $structure['total_units'] > 0
                    ? round($structure['total_credit_hours'] / $structure['total_units'], 1)
                    : 0,
            ],
            'structure_insights' => $this->generateStructureInsights($structure),
        ];
    }

    /**
     * Format progress summary
     */
    protected function formatProgressSummary(array $progress): array
    {
        return [
            'unit_progress' => [
                'overview' => $progress['units'],
                'visual_progress' => [
                    'completed_percentage' => $progress['units']['completion_percentage'],
                    'current_percentage' => $progress['units']['total'] > 0
                        ? round(($progress['units']['current'] / $progress['units']['total']) * 100, 1)
                        : 0,
                    'remaining_percentage' => $progress['units']['total'] > 0
                        ? round(($progress['units']['remaining'] / $progress['units']['total']) * 100, 1)
                        : 0,
                ],
                'status_indicators' => [
                    'progress_status' => $this->getProgressStatus($progress['units']['completion_percentage']),
                    'progress_color' => $this->getProgressColor($progress['units']['completion_percentage']),
                    'milestone_reached' => $this->getMilestoneReached($progress['units']['completion_percentage']),
                ],
            ],
            'credit_progress' => [
                'overview' => $progress['credits'],
                'visual_progress' => [
                    'completed_percentage' => $progress['credits']['completion_percentage'],
                    'current_percentage' => $progress['credits']['total'] > 0
                        ? round(($progress['credits']['current'] / $progress['credits']['total']) * 100, 1)
                        : 0,
                    'remaining_percentage' => $progress['credits']['total'] > 0
                        ? round(($progress['credits']['remaining'] / $progress['credits']['total']) * 100, 1)
                        : 0,
                ],
                'credit_efficiency' => $this->calculateCreditEfficiency($progress['credits']),
            ],
            'overall_assessment' => $this->generateOverallAssessment($progress),
        ];
    }

    /**
     * Format completion status by category
     */
    protected function formatCompletionStatus(array $completionStatus): array
    {
        return collect($completionStatus)->map(function ($categoryStatus) {
            return [
                'category' => $categoryStatus['category'],
                'category_display' => $this->getCategoryDisplay($categoryStatus['category']),
                'progress' => [
                    'total_units' => $categoryStatus['total_units'],
                    'completed_units' => $categoryStatus['completed_units'],
                    'remaining_units' => $categoryStatus['remaining_units'],
                    'completion_percentage' => $categoryStatus['completion_percentage'],
                ],
                'status' => [
                    'is_complete' => $categoryStatus['is_complete'],
                    'completion_status' => $this->getCategoryCompletionStatus($categoryStatus),
                    'status_color' => $this->getCategoryStatusColor($categoryStatus),
                    'priority_level' => $this->getCategoryPriority($categoryStatus['category']),
                ],
                'visual_indicators' => [
                    'progress_bar_color' => $this->getProgressBarColor($categoryStatus['completion_percentage']),
                    'icon' => $this->getCategoryIcon($categoryStatus['category']),
                    'badge' => $categoryStatus['is_complete'] ? 'complete' : 'in_progress',
                ],
            ];
        })->toArray();
    }

    /**
     * Generate visual indicators
     */
    protected function generateVisualIndicators(): array
    {
        return [
            'progress_rings' => [
                'overall_completion' => [
                    'percentage' => $this->resource['progress_summary']['units']['completion_percentage'],
                    'color' => $this->getProgressColor($this->resource['progress_summary']['units']['completion_percentage']),
                    'stroke_width' => 8,
                ],
                'credit_completion' => [
                    'percentage' => $this->resource['progress_summary']['credits']['completion_percentage'],
                    'color' => $this->getProgressColor($this->resource['progress_summary']['credits']['completion_percentage']),
                    'stroke_width' => 6,
                ],
            ],
            'category_indicators' => collect($this->resource['completion_status'])->map(function ($category) {
                return [
                    'category' => $category['category'],
                    'percentage' => $category['completion_percentage'],
                    'color' => $this->getCategoryColor($category['category']),
                    'is_complete' => $category['is_complete'],
                ];
            })->toArray(),
            'milestone_markers' => $this->generateMilestoneMarkers(),
        ];
    }

    /**
     * Format category breakdown
     */
    protected function formatCategoryBreakdown(array $categories): array
    {
        return collect($categories)->map(function ($count, $category) {
            return [
                'category' => $category,
                'category_display' => $this->getCategoryDisplay($category),
                'unit_count' => $count,
                'icon' => $this->getCategoryIcon($category),
                'color' => $this->getCategoryColor($category),
                'description' => $this->getCategoryDescription($category),
            ];
        })->values()->toArray();
    }

    /**
     * Format year level breakdown
     */
    protected function formatYearLevelBreakdown(array $yearLevels): array
    {
        return collect($yearLevels)->map(function ($count, $yearLevel) {
            return [
                'year_level' => $yearLevel,
                'year_display' => 'Year '.$yearLevel,
                'unit_count' => $count,
                'difficulty_level' => $this->getDifficultyLevel($yearLevel),
            ];
        })->values()->toArray();
    }

    /**
     * Format semester breakdown
     */
    protected function formatSemesterBreakdown(array $semesters): array
    {
        return collect($semesters)->map(function ($count, $semester) {
            return [
                'semester' => $semester,
                'semester_display' => 'Semester '.$semester,
                'unit_count' => $count,
                'typical_timing' => $this->getTypicalTiming($semester),
            ];
        })->values()->toArray();
    }

    /**
     * Generate structure insights
     */
    protected function generateStructureInsights(array $structure): array
    {
        return [
            'curriculum_balance' => $this->analyzeCurriculumBalance($structure['by_category']),
            'progression_pattern' => $this->analyzeProgressionPattern($structure['by_year_level']),
            'workload_distribution' => $this->analyzeWorkloadDistribution($structure['by_semester']),
            'recommendations' => $this->generateStructureRecommendations($structure),
        ];
    }

    /**
     * Calculate credit efficiency
     */
    protected function calculateCreditEfficiency(array $credits): array
    {
        $efficiency = $credits['total'] > 0
            ? round(($credits['completed'] / $credits['total']) * 100, 1)
            : 0;

        return [
            'efficiency_percentage' => $efficiency,
            'efficiency_status' => $this->getEfficiencyStatus($efficiency),
            'credits_per_unit_average' => $credits['completed'] > 0
                ? round($credits['completed'] / max(1, $this->resource['progress_summary']['units']['completed']), 1)
                : 0,
        ];
    }

    /**
     * Generate overall assessment
     */
    protected function generateOverallAssessment(array $progress): array
    {
        $unitProgress = $progress['units']['completion_percentage'];
        $creditProgress = $progress['credits']['completion_percentage'];
        $overallProgress = ($unitProgress + $creditProgress) / 2;

        return [
            'overall_progress_percentage' => round($overallProgress, 1),
            'progress_status' => $this->getProgressStatus($overallProgress),
            'academic_standing' => $this->getAcademicStanding($overallProgress),
            'next_milestones' => $this->getNextMilestones($overallProgress),
            'completion_forecast' => $this->generateCompletionForecast($progress),
        ];
    }

    /**
     * Helper methods for formatting and calculations
     */
    protected function getDegreeTypeDisplay(string $degreeType): string
    {
        return match ($degreeType) {
            'bachelor' => 'Bachelor\'s Degree',
            'master' => 'Master\'s Degree',
            'doctorate' => 'Doctoral Degree',
            'diploma' => 'Diploma',
            'certificate' => 'Certificate',
            default => ucfirst($degreeType),
        };
    }

    protected function getCategoryDisplay(string $category): string
    {
        return match ($category) {
            'core' => 'Core Units',
            'elective' => 'Elective Units',
            'specialization' => 'Specialization Units',
            'general_education' => 'General Education',
            'capstone' => 'Capstone Project',
            default => ucfirst(str_replace('_', ' ', $category)),
        };
    }

    protected function getCategoryIcon(string $category): string
    {
        return match ($category) {
            'core' => 'academic-cap',
            'elective' => 'puzzle-piece',
            'specialization' => 'star',
            'general_education' => 'book-open',
            'capstone' => 'trophy',
            default => 'document-text',
        };
    }

    protected function getCategoryColor(string $category): string
    {
        return match ($category) {
            'core' => '#3b82f6',           // Blue
            'elective' => '#10b981',       // Emerald
            'specialization' => '#f59e0b', // Amber
            'general_education' => '#8b5cf6', // Purple
            'capstone' => '#ef4444',       // Red
            default => '#6b7280',          // Gray
        };
    }

    protected function getCategoryDescription(string $category): string
    {
        return match ($category) {
            'core' => 'Essential units required for your degree',
            'elective' => 'Optional units to broaden your knowledge',
            'specialization' => 'Units specific to your chosen specialization',
            'general_education' => 'Foundational units across disciplines',
            'capstone' => 'Final project demonstrating your learning',
            default => 'Units in this category',
        };
    }

    protected function getProgressStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'excellent',
            $percentage >= 70 => 'good',
            $percentage >= 50 => 'satisfactory',
            $percentage >= 25 => 'progressing',
            default => 'beginning',
        };
    }

    protected function getProgressColor(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => '#22c55e', // Green
            $percentage >= 60 => '#3b82f6', // Blue
            $percentage >= 40 => '#f59e0b', // Amber
            $percentage >= 20 => '#f97316', // Orange
            default => '#ef4444',           // Red
        };
    }

    protected function getMilestoneReached(float $percentage): ?string
    {
        return match (true) {
            $percentage >= 75 => 'approaching_graduation',
            $percentage >= 50 => 'halfway_complete',
            $percentage >= 25 => 'quarter_complete',
            default => null,
        };
    }

    protected function getCategoryCompletionStatus(array $categoryStatus): string
    {
        if ($categoryStatus['is_complete']) {
            return 'complete';
        }

        $percentage = $categoryStatus['completion_percentage'];

        return match (true) {
            $percentage >= 75 => 'nearly_complete',
            $percentage >= 50 => 'in_progress',
            $percentage >= 25 => 'started',
            default => 'not_started',
        };
    }

    protected function getCategoryStatusColor(array $categoryStatus): string
    {
        if ($categoryStatus['is_complete']) {
            return '#22c55e'; // Green
        }

        return $this->getProgressColor($categoryStatus['completion_percentage']);
    }

    protected function getCategoryPriority(string $category): string
    {
        return match ($category) {
            'core' => 'high',
            'specialization' => 'high',
            'general_education' => 'medium',
            'elective' => 'low',
            'capstone' => 'high',
            default => 'medium',
        };
    }

    protected function getProgressBarColor(float $percentage): string
    {
        return $this->getProgressColor($percentage);
    }

    protected function generateMilestoneMarkers(): array
    {
        return [
            ['percentage' => 25, 'label' => '25% Complete', 'color' => '#f97316'],
            ['percentage' => 50, 'label' => 'Halfway Point', 'color' => '#f59e0b'],
            ['percentage' => 75, 'label' => '75% Complete', 'color' => '#3b82f6'],
            ['percentage' => 90, 'label' => 'Near Completion', 'color' => '#22c55e'],
        ];
    }

    protected function getDifficultyLevel(int $yearLevel): string
    {
        return match ($yearLevel) {
            1 => 'introductory',
            2 => 'intermediate',
            3 => 'advanced',
            4 => 'expert',
            default => 'intermediate',
        };
    }

    protected function getTypicalTiming(int $semester): string
    {
        return $semester % 2 === 1 ? 'first_semester' : 'second_semester';
    }

    protected function analyzeCurriculumBalance(array $categories): string
    {
        $coreCount = $categories['core'] ?? 0;
        $electiveCount = $categories['elective'] ?? 0;
        $total = array_sum($categories);

        $corePercentage = $total > 0 ? ($coreCount / $total) * 100 : 0;

        return match (true) {
            $corePercentage >= 70 => 'core_heavy',
            $corePercentage >= 50 => 'balanced',
            default => 'elective_heavy',
        };
    }

    protected function analyzeProgressionPattern(array $yearLevels): string
    {
        $counts = array_values($yearLevels);
        $isIncreasing = true;

        for ($i = 1; $i < count($counts); $i++) {
            if ($counts[$i] <= $counts[$i - 1]) {
                $isIncreasing = false;
                break;
            }
        }

        return $isIncreasing ? 'progressive' : 'distributed';
    }

    protected function analyzeWorkloadDistribution(array $semesters): string
    {
        $counts = array_values($semesters);
        $variance = $this->calculateVariance($counts);

        return $variance < 2 ? 'even' : 'uneven';
    }

    protected function generateStructureRecommendations(array $structure): array
    {
        return [
            'Plan your core units early to meet prerequisites',
            'Balance challenging and easier units each semester',
            'Consider your specialization requirements when selecting electives',
            'Leave some flexibility for unexpected opportunities',
        ];
    }

    protected function getEfficiencyStatus(float $efficiency): string
    {
        return match (true) {
            $efficiency >= 90 => 'excellent',
            $efficiency >= 80 => 'good',
            $efficiency >= 70 => 'satisfactory',
            default => 'needs_improvement',
        };
    }

    protected function getAcademicStanding(float $progress): string
    {
        return match (true) {
            $progress >= 80 => 'excellent_progress',
            $progress >= 60 => 'good_progress',
            $progress >= 40 => 'satisfactory_progress',
            default => 'needs_acceleration',
        };
    }

    protected function getNextMilestones(float $progress): array
    {
        $milestones = [];

        if ($progress < 25) {
            $milestones[] = 'Complete 25% of your program';
        } elseif ($progress < 50) {
            $milestones[] = 'Reach the halfway point';
        } elseif ($progress < 75) {
            $milestones[] = 'Complete 75% of your program';
        } else {
            $milestones[] = 'Prepare for graduation';
        }

        return $milestones;
    }

    protected function generateCompletionForecast(array $progress): array
    {
        $unitsRemaining = $progress['units']['remaining'];
        $averageUnitsPerSemester = 4; // Typical full-time load

        $semestersRemaining = $unitsRemaining > 0
            ? ceil($unitsRemaining / $averageUnitsPerSemester)
            : 0;

        return [
            'estimated_semesters_remaining' => $semestersRemaining,
            'estimated_completion_date' => $semestersRemaining > 0
                ? now()->addMonths($semestersRemaining * 6)->format('Y-m-d')
                : null,
            'on_track_for_expected_graduation' => $semestersRemaining <= 4, // Assuming 2 years is typical remaining
        ];
    }

    protected function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn ($x) => pow($x - $mean, 2), $values)) / count($values);

        return $variance;
    }
}
