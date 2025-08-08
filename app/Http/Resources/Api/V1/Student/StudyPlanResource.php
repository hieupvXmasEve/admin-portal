<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'current_semester' => $this->resource['current_semester'],
            'academic_progress' => [
                'completed_units' => $this->formatCompletedUnits($this->resource['completed_units']),
                'current_enrollments' => $this->formatCurrentEnrollments($this->resource['current_enrollments']),
                'remaining_requirements' => $this->formatRemainingRequirements($this->resource['remaining_requirements']),
            ],
            'graduation_timeline' => $this->formatGraduationTimeline($this->resource['graduation_timeline']),
            'recommendations' => [
                'next_units' => $this->formatRecommendedUnits($this->resource['recommended_next_units']),
                'study_tips' => $this->getStudyTips(),
                'planning_advice' => $this->getPlanningAdvice(),
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format completed units
     */
    protected function formatCompletedUnits(array $completedUnits): array
    {
        return [
            'total_count' => count($completedUnits),
            'units' => collect($completedUnits)->map(function ($unit) {
                return [
                    'unit_code' => $unit['unit_code'],
                    'unit_name' => $unit['unit_name'],
                    'credit_hours' => $unit['credit_hours'],
                    'grade' => [
                        'letter' => $unit['grade'],
                        'display' => $unit['grade'],
                        'color' => $this->getGradeColor($unit['grade']),
                    ],
                    'semester' => $unit['semester'],
                    'completion_date' => $unit['completion_date'],
                    'academic_year' => $this->getAcademicYear($unit['completion_date']),
                ];
            })->toArray(),
            'total_credit_hours' => collect($completedUnits)->sum('credit_hours'),
            'grade_distribution' => $this->calculateGradeDistribution($completedUnits),
        ];
    }

    /**
     * Format current enrollments
     */
    protected function formatCurrentEnrollments(array $currentEnrollments): array
    {
        return [
            'total_count' => count($currentEnrollments),
            'units' => collect($currentEnrollments)->map(function ($unit) {
                return [
                    'unit_code' => $unit['unit_code'],
                    'unit_name' => $unit['unit_name'],
                    'credit_hours' => $unit['credit_hours'],
                    'registration_date' => $unit['registration_date'],
                    'status' => 'enrolled',
                    'status_display' => 'Currently Enrolled',
                ];
            })->toArray(),
            'total_credit_hours' => collect($currentEnrollments)->sum('credit_hours'),
            'enrollment_status' => $this->getEnrollmentStatus(count($currentEnrollments)),
        ];
    }

    /**
     * Format remaining requirements
     */
    protected function formatRemainingRequirements(array $remainingRequirements): array
    {
        return [
            'core_units' => [
                'count' => count($remainingRequirements['core_units']),
                'units' => $remainingRequirements['core_units'],
            ],
            'elective_units' => [
                'count' => count($remainingRequirements['elective_units']),
                'units' => $remainingRequirements['elective_units'],
            ],
            'total_credits_remaining' => $remainingRequirements['total_credits_remaining'],
            'completion_estimate' => $this->estimateCompletion($remainingRequirements['total_credits_remaining']),
        ];
    }

    /**
     * Format graduation timeline
     */
    protected function formatGraduationTimeline(array $timeline): array
    {
        return [
            'progress' => [
                'credits_remaining' => $timeline['credits_remaining'],
                'semesters_remaining' => $timeline['semesters_remaining'],
                'estimated_graduation_date' => $timeline['estimated_graduation_date'],
                'on_track' => $timeline['on_track'],
            ],
            'status' => [
                'timeline_status' => $timeline['on_track'] ? 'on_track' : 'behind_schedule',
                'timeline_color' => $timeline['on_track'] ? '#22c55e' : '#f59e0b',
                'urgency_level' => $this->getUrgencyLevel($timeline['semesters_remaining']),
            ],
            'milestones' => $this->generateMilestones($timeline),
        ];
    }

    /**
     * Format recommended units
     */
    protected function formatRecommendedUnits(array $recommendedUnits): array
    {
        return collect($recommendedUnits)->map(function ($unit) {
            return [
                'unit_code' => $unit['unit_code'] ?? '',
                'unit_name' => $unit['unit_name'] ?? '',
                'credit_hours' => $unit['credit_hours'] ?? 0,
                'recommendation_reason' => $unit['reason'] ?? 'Fits your academic progression',
                'priority' => $unit['priority'] ?? 'medium',
                'prerequisites_met' => $unit['prerequisites_met'] ?? true,
            ];
        })->toArray();
    }

    /**
     * Get grade color
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
     * Get academic year from date
     */
    protected function getAcademicYear(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        $year = \Carbon\Carbon::parse($date)->year;

        return (string) $year;
    }

    /**
     * Calculate grade distribution
     */
    protected function calculateGradeDistribution(array $units): array
    {
        $grades = collect($units)->pluck('grade')->countBy();

        return $grades->map(function ($count, $grade) use ($units) {
            return [
                'grade' => $grade,
                'count' => $count,
                'percentage' => round(($count / count($units)) * 100, 1),
            ];
        })->values()->toArray();
    }

    /**
     * Get enrollment status
     */
    protected function getEnrollmentStatus(int $unitCount): string
    {
        return match (true) {
            $unitCount === 0 => 'not_enrolled',
            $unitCount < 3 => 'part_time',
            $unitCount >= 4 => 'full_time',
            default => 'enrolled',
        };
    }

    /**
     * Estimate completion time
     */
    protected function estimateCompletion(int $creditsRemaining): array
    {
        $averageCreditsPerSemester = 18;
        $semestersNeeded = $creditsRemaining > 0 ? ceil($creditsRemaining / $averageCreditsPerSemester) : 0;

        return [
            'semesters_needed' => $semestersNeeded,
            'years_needed' => ceil($semestersNeeded / 2),
            'estimated_date' => $semestersNeeded > 0
                ? now()->addMonths($semestersNeeded * 6)->format('Y-m-d')
                : null,
        ];
    }

    /**
     * Get urgency level
     */
    protected function getUrgencyLevel(int $semestersRemaining): string
    {
        return match (true) {
            $semestersRemaining <= 1 => 'high',
            $semestersRemaining <= 3 => 'medium',
            default => 'low',
        };
    }

    /**
     * Generate milestones
     */
    protected function generateMilestones(array $timeline): array
    {
        $milestones = [];

        if ($timeline['semesters_remaining'] > 0) {
            $milestones[] = [
                'title' => 'Next Semester Registration',
                'description' => 'Plan and register for your next semester',
                'target_date' => now()->addMonths(3)->format('Y-m-d'),
                'type' => 'registration',
            ];

            if ($timeline['semesters_remaining'] <= 2) {
                $milestones[] = [
                    'title' => 'Graduation Application',
                    'description' => 'Apply for graduation',
                    'target_date' => now()->addMonths(6)->format('Y-m-d'),
                    'type' => 'graduation',
                ];
            }
        }

        return $milestones;
    }

    /**
     * Get study tips
     */
    protected function getStudyTips(): array
    {
        return [
            'Plan your semester schedule early to ensure course availability',
            'Balance challenging courses with easier ones each semester',
            'Consider summer sessions to accelerate your progress',
            'Meet with your academic advisor regularly',
            'Keep track of prerequisite requirements for future courses',
        ];
    }

    /**
     * Get planning advice
     */
    protected function getPlanningAdvice(): array
    {
        return [
            'Review your degree requirements regularly',
            'Plan for prerequisite chains early in your program',
            'Consider your work and personal commitments when planning course loads',
            'Explore elective options that align with your career goals',
            'Stay informed about course offerings and schedule changes',
        ];
    }
}
