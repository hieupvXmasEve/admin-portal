<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemesterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'current_semester' => $this->formatCurrentSemester($this->resource['current_semester']),
            'upcoming_semesters' => $this->formatUpcomingSemesters($this->resource['upcoming_semesters']),
            'past_semesters' => $this->formatPastSemesters($this->resource['past_semesters']),
            'all_semesters' => $this->formatAllSemesters($this->resource['all_semesters']),
            'semester_navigation' => $this->generateSemesterNavigation(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format current semester
     */
    protected function formatCurrentSemester(?array $currentSemester): ?array
    {
        if (!$currentSemester) {
            return null;
        }

        return [
            'semester_info' => [
                'id' => $currentSemester['id'],
                'name' => $currentSemester['name'],
                'code' => $currentSemester['code'],
                'display_name' => $currentSemester['name'] . ' (' . $currentSemester['code'] . ')',
            ],
            'dates' => [
                'start_date' => $currentSemester['start_date'],
                'end_date' => $currentSemester['end_date'],
                'duration_weeks' => $currentSemester['duration_weeks'],
                'formatted_duration' => $this->formatDateRange($currentSemester['start_date'], $currentSemester['end_date']),
            ],
            'status' => [
                'is_active' => $currentSemester['is_active'],
                'status' => $currentSemester['status'],
                'status_display' => $this->getStatusDisplay($currentSemester['status']),
                'status_color' => $this->getStatusColor($currentSemester['status']),
            ],
            'timeline' => [
                'days_until_end' => $currentSemester['days_until_end'],
                'progress_percentage' => $this->calculateSemesterProgress($currentSemester),
                'time_remaining_display' => $this->formatTimeRemaining($currentSemester['days_until_end']),
            ],
            'registration' => [
                'registration_period' => $currentSemester['registration_period'],
                'is_registration_open' => $currentSemester['registration_period']['is_open'],
                'registration_status' => $this->getRegistrationStatus($currentSemester['registration_period']),
            ],
        ];
    }

    /**
     * Format upcoming semesters
     */
    protected function formatUpcomingSemesters(array $upcomingSemesters): array
    {
        return collect($upcomingSemesters)->map(function ($semester) {
            return [
                'semester_info' => [
                    'id' => $semester['id'],
                    'name' => $semester['name'],
                    'code' => $semester['code'],
                    'display_name' => $semester['name'] . ' (' . $semester['code'] . ')',
                ],
                'dates' => [
                    'start_date' => $semester['start_date'],
                    'end_date' => $semester['end_date'],
                    'duration_weeks' => $semester['duration_weeks'],
                    'formatted_duration' => $this->formatDateRange($semester['start_date'], $semester['end_date']),
                ],
                'countdown' => [
                    'days_until_start' => $semester['days_until_start'],
                    'countdown_display' => $this->formatCountdown($semester['days_until_start']),
                    'is_upcoming' => true,
                ],
                'registration' => [
                    'registration_period' => $semester['registration_period'],
                    'is_registration_open' => $semester['registration_period']['is_open'],
                    'registration_opens_in' => $this->calculateRegistrationCountdown($semester['registration_period']),
                ],
                'planning' => [
                    'can_plan' => true,
                    'planning_priority' => $this->getPlanningPriority($semester['days_until_start']),
                ],
            ];
        })->toArray();
    }

    /**
     * Format past semesters
     */
    protected function formatPastSemesters(array $pastSemesters): array
    {
        return collect($pastSemesters)->map(function ($semester) {
            return [
                'semester_info' => [
                    'id' => $semester['id'],
                    'name' => $semester['name'],
                    'code' => $semester['code'],
                    'display_name' => $semester['name'] . ' (' . $semester['code'] . ')',
                ],
                'dates' => [
                    'start_date' => $semester['start_date'],
                    'end_date' => $semester['end_date'],
                    'duration_weeks' => $semester['duration_weeks'],
                    'formatted_duration' => $this->formatDateRange($semester['start_date'], $semester['end_date']),
                ],
                'completion' => [
                    'is_completed' => true,
                    'completion_status' => 'completed',
                    'academic_year' => $this->getAcademicYear($semester['start_date']),
                ],
                'access' => [
                    'can_view_records' => true,
                    'can_view_grades' => true,
                    'historical_data_available' => true,
                ],
            ];
        })->toArray();
    }

    /**
     * Format all semesters
     */
    protected function formatAllSemesters(array $allSemesters): array
    {
        return collect($allSemesters)->map(function ($semester) {
            return [
                'id' => $semester['id'],
                'name' => $semester['name'],
                'code' => $semester['code'],
                'start_date' => $semester['start_date'],
                'end_date' => $semester['end_date'],
                'is_active' => $semester['is_active'],
                'status' => $semester['status'],
                'status_display' => $this->getStatusDisplay($semester['status']),
                'academic_year' => $this->getAcademicYear($semester['start_date']),
                'semester_type' => $this->getSemesterType($semester['name']),
                'quick_actions' => $this->getQuickActions($semester),
            ];
        })->toArray();
    }

    /**
     * Generate semester navigation
     */
    protected function generateSemesterNavigation(): array
    {
        return [
            'navigation_options' => [
                'view_current' => 'View Current Semester',
                'plan_upcoming' => 'Plan Upcoming Semesters',
                'review_past' => 'Review Past Semesters',
                'compare_semesters' => 'Compare Semester Performance',
            ],
            'quick_filters' => [
                'current_year' => 'Current Academic Year',
                'last_year' => 'Previous Academic Year',
                'all_completed' => 'All Completed Semesters',
                'future_planning' => 'Future Planning',
            ],
            'semester_timeline' => $this->generateSemesterTimeline(),
        ];
    }

    /**
     * Calculate semester progress
     */
    protected function calculateSemesterProgress(array $semester): float
    {
        $startDate = \Carbon\Carbon::parse($semester['start_date']);
        $endDate = \Carbon\Carbon::parse($semester['end_date']);
        $now = now();

        if ($now < $startDate) {
            return 0;
        } elseif ($now > $endDate) {
            return 100;
        }

        $totalDays = $startDate->diffInDays($endDate);
        $elapsedDays = $startDate->diffInDays($now);

        return $totalDays > 0 ? round(($elapsedDays / $totalDays) * 100, 1) : 0;
    }

    /**
     * Format date range
     */
    protected function formatDateRange(string $startDate, string $endDate): string
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        if ($start->year === $end->year) {
            return $start->format('M j') . ' - ' . $end->format('M j, Y');
        } else {
            return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
        }
    }

    /**
     * Format time remaining
     */
    protected function formatTimeRemaining(int $days): string
    {
        if ($days <= 0) {
            return 'Semester ended';
        } elseif ($days === 1) {
            return '1 day remaining';
        } elseif ($days < 7) {
            return "{$days} days remaining";
        } elseif ($days < 30) {
            $weeks = ceil($days / 7);
            return $weeks === 1 ? '1 week remaining' : "{$weeks} weeks remaining";
        } else {
            $months = ceil($days / 30);
            return $months === 1 ? '1 month remaining' : "{$months} months remaining";
        }
    }

    /**
     * Format countdown
     */
    protected function formatCountdown(int $days): string
    {
        if ($days <= 0) {
            return 'Starting soon';
        } elseif ($days === 1) {
            return 'Starts tomorrow';
        } elseif ($days < 7) {
            return "Starts in {$days} days";
        } elseif ($days < 30) {
            $weeks = ceil($days / 7);
            return $weeks === 1 ? 'Starts in 1 week' : "Starts in {$weeks} weeks";
        } else {
            $months = ceil($days / 30);
            return $months === 1 ? 'Starts in 1 month' : "Starts in {$months} months";
        }
    }

    /**
     * Calculate registration countdown
     */
    protected function calculateRegistrationCountdown(array $registrationPeriod): ?array
    {
        if (!$registrationPeriod['start'] || $registrationPeriod['is_open']) {
            return null;
        }

        $registrationStart = \Carbon\Carbon::parse($registrationPeriod['start']);
        $daysUntil = now()->diffInDays($registrationStart, false);

        if ($daysUntil < 0) {
            return null;
        }

        return [
            'days_until_registration' => $daysUntil,
            'countdown_display' => $this->formatCountdown($daysUntil),
            'registration_reminder' => $daysUntil <= 7,
        ];
    }

    /**
     * Get status display
     */
    protected function getStatusDisplay(string $status): string
    {
        return match ($status) {
            'current' => 'Current Semester',
            'upcoming' => 'Upcoming Semester',
            'completed' => 'Completed Semester',
            default => ucfirst($status),
        };
    }

    /**
     * Get status color
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'current' => '#22c55e',   // Green
            'upcoming' => '#3b82f6',  // Blue
            'completed' => '#6b7280', // Gray
            default => '#6b7280',     // Gray
        };
    }

    /**
     * Get registration status
     */
    protected function getRegistrationStatus(array $registrationPeriod): string
    {
        if (!$registrationPeriod['start'] || !$registrationPeriod['end']) {
            return 'not_available';
        }

        $now = now();
        $start = \Carbon\Carbon::parse($registrationPeriod['start']);
        $end = \Carbon\Carbon::parse($registrationPeriod['end']);

        if ($now < $start) {
            return 'not_open_yet';
        } elseif ($now > $end) {
            return 'closed';
        } else {
            return 'open';
        }
    }

    /**
     * Get planning priority
     */
    protected function getPlanningPriority(int $daysUntilStart): string
    {
        return match (true) {
            $daysUntilStart <= 30 => 'high',
            $daysUntilStart <= 90 => 'medium',
            default => 'low',
        };
    }

    /**
     * Get academic year
     */
    protected function getAcademicYear(string $date): string
    {
        $carbon = \Carbon\Carbon::parse($date);
        $year = $carbon->year;
        
        // If semester starts in second half of year, it's the start of academic year
        if ($carbon->month >= 7) {
            return $year . '/' . ($year + 1);
        } else {
            return ($year - 1) . '/' . $year;
        }
    }

    /**
     * Get semester type
     */
    protected function getSemesterType(string $name): string
    {
        $name = strtolower($name);
        
        if (str_contains($name, 'summer')) {
            return 'summer';
        } elseif (str_contains($name, 'spring') || str_contains($name, 'semester 1')) {
            return 'spring';
        } elseif (str_contains($name, 'fall') || str_contains($name, 'autumn') || str_contains($name, 'semester 2')) {
            return 'fall';
        } else {
            return 'regular';
        }
    }

    /**
     * Get quick actions for semester
     */
    protected function getQuickActions(array $semester): array
    {
        $actions = [];
        
        switch ($semester['status']) {
            case 'current':
                $actions = [
                    'view_timetable' => 'View Timetable',
                    'view_grades' => 'View Grades',
                    'view_attendance' => 'View Attendance',
                    'manage_enrollment' => 'Manage Enrollment',
                ];
                break;
                
            case 'upcoming':
                $actions = [
                    'plan_enrollment' => 'Plan Enrollment',
                    'view_course_catalog' => 'View Course Catalog',
                    'set_reminders' => 'Set Reminders',
                ];
                break;
                
            case 'completed':
                $actions = [
                    'view_transcript' => 'View Transcript',
                    'view_final_grades' => 'View Final Grades',
                    'download_records' => 'Download Records',
                ];
                break;
        }
        
        return $actions;
    }

    /**
     * Generate semester timeline
     */
    protected function generateSemesterTimeline(): array
    {
        $timeline = [];
        
        // This would generate a visual timeline of semesters
        // showing past, current, and future semesters
        
        foreach ($this->resource['all_semesters'] as $semester) {
            $timeline[] = [
                'id' => $semester['id'],
                'name' => $semester['name'],
                'start_date' => $semester['start_date'],
                'status' => $semester['status'],
                'position' => $this->calculateTimelinePosition($semester['start_date']),
            ];
        }
        
        return $timeline;
    }

    /**
     * Calculate timeline position
     */
    protected function calculateTimelinePosition(string $date): string
    {
        $semesterDate = \Carbon\Carbon::parse($date);
        $now = now();
        
        if ($semesterDate < $now->subYear()) {
            return 'far_past';
        } elseif ($semesterDate < $now) {
            return 'recent_past';
        } elseif ($semesterDate < $now->addMonths(6)) {
            return 'near_future';
        } else {
            return 'far_future';
        }
    }
}
