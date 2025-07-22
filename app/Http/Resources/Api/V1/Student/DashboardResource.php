<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'current_semester' => $this->formatCurrentSemester($this->resource['current_semester']),
            'gpa_data' => $this->formatGPAData($this->resource['gpa_data']),
            'credit_progress' => $this->formatCreditProgress($this->resource['credit_progress']),
            'academic_holds' => $this->formatAcademicHolds($this->resource['academic_holds']),
            'upcoming_assessments' => $this->formatUpcomingAssessments($this->resource['upcoming_assessments']),
            'enrollment_status' => $this->resource['enrollment_status'],
            'quick_stats' => $this->formatQuickStats($this->resource['quick_stats']),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Format current semester data
     */
    protected function formatCurrentSemester(array $semesterData): array
    {
        return [
            'semester' => $semesterData['semester'],
            'enrollment' => $semesterData['enrollment'],
            'course_summary' => [
                'registered_courses' => $semesterData['registered_courses'],
                'total_credits' => $semesterData['total_credits'],
            ],
            'courses' => $semesterData['courses'] ?? [],
        ];
    }

    /**
     * Format GPA data
     */
    protected function formatGPAData(array $gpaData): array
    {
        return [
            'current' => $gpaData['current_gpa'] ?? null,
            'trend' => $gpaData['gpa_trend'] ?? null,
            'distribution' => $gpaData['grade_distribution'] ?? null,
            'standing' => $gpaData['academic_standing'] ?? null,
        ];
    }

    /**
     * Format credit progress data
     */
    protected function formatCreditProgress(array $progressData): array
    {
        return [
            'overall' => $progressData['overall_progress'] ?? null,
            'by_category' => $progressData['category_progress'] ?? [],
            'semester_breakdown' => $progressData['semester_progress'] ?? [],
            'remaining_requirements' => $progressData['remaining_requirements'] ?? [],
            'graduation_readiness' => $progressData['graduation_readiness'] ?? null,
        ];
    }

    /**
     * Format academic holds data
     */
    protected function formatAcademicHolds(array $holdsData): array
    {
        return [
            'summary' => [
                'total_holds' => $holdsData['total_holds'],
                'blocking_registration' => $holdsData['blocking_registration'],
                'blocking_graduation' => $holdsData['blocking_graduation'],
            ],
            'holds' => $holdsData['holds'],
        ];
    }

    /**
     * Format upcoming assessments data
     */
    protected function formatUpcomingAssessments(array $assessmentsData): array
    {
        return [
            'summary' => [
                'total_upcoming' => $assessmentsData['total_upcoming'],
            ],
            'assessments' => collect($assessmentsData['assessments'])->map(function ($assessment) {
                return [
                    'id' => $assessment['id'],
                    'title' => $assessment['title'],
                    'type' => $assessment['type'],
                    'course' => [
                        'code' => $assessment['course_code'],
                        'name' => $assessment['course_name'],
                    ],
                    'due_date' => $assessment['due_date'],
                    'max_score' => $assessment['max_score'],
                    'weight' => $assessment['weight'],
                    'status' => $assessment['status'],
                    'urgency' => $this->calculateUrgency($assessment['due_date']),
                ];
            }),
        ];
    }

    /**
     * Format quick stats data
     */
    protected function formatQuickStats(array $statsData): array
    {
        return [
            'academic_performance' => [
                'total_courses_completed' => $statsData['total_courses_completed'],
                'current_semester_courses' => $statsData['current_semester_courses'],
                'attendance_rate' => $statsData['attendance_rate'],
            ],
        ];
    }

    /**
     * Calculate urgency level for assessments
     */
    protected function calculateUrgency(?string $dueDate): string
    {
        if (!$dueDate) {
            return 'unknown';
        }

        $due = \Carbon\Carbon::parse($dueDate);
        $now = \Carbon\Carbon::now();
        $daysUntilDue = $now->diffInDays($due, false);

        return match (true) {
            $daysUntilDue < 0 => 'overdue',
            $daysUntilDue <= 1 => 'critical',
            $daysUntilDue <= 3 => 'high',
            $daysUntilDue <= 7 => 'medium',
            default => 'low',
        };
    }
}
