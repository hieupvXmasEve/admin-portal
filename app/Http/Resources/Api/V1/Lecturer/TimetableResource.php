<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimetableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'period' => [
                'start_date' => $this->resource['period']['start_date'],
                'end_date' => $this->resource['period']['end_date'],
                'view' => $this->resource['period']['view'],
                'formatted_period' => $this->getFormattedPeriod(),
            ],

            'sessions' => $this->formatSessionsForView(),

            'summary' => [
                'total_sessions' => $this->resource['summary']['total_sessions'],
                'completed_sessions' => $this->resource['summary']['completed_sessions'],
                'upcoming_sessions' => $this->resource['summary']['upcoming_sessions'],
                'cancelled_sessions' => $this->resource['summary']['cancelled_sessions'],
                'total_teaching_hours' => $this->resource['summary']['total_teaching_hours'],
                'period_days' => $this->resource['summary']['period_days'],
                'average_sessions_per_day' => $this->getAverageSessionsPerDay(),
                'busiest_day' => $this->getBusiestDay(),
            ],

            'conflicts' => collect($this->resource['conflicts'])->map(function ($conflict) {
                return [
                    'type' => $conflict['type'],
                    'date' => $conflict['date'],
                    'message' => $conflict['message'],
                    'sessions' => $conflict['sessions'],
                    'severity' => $this->getConflictSeverity($conflict),
                ];
            }),

            'availability' => $this->resource['availability'],

            // Assigned exam-resit invigilation duty, already formatted by
            // InvigilationDutyQuery (ACAD-RET-001 slice 9).
            'invigilation_duties' => $this->resource['invigilation_duties'] ?? [],

            'insights' => $this->generateInsights(),

            'navigation' => $this->getNavigationInfo(),
        ];
    }

    /**
     * Format sessions based on view type
     */
    protected function formatSessionsForView(): array
    {
        $view = $this->resource['period']['view'];
        $sessions = $this->resource['sessions'];

        return match ($view) {
            'day' => $this->formatForDayView($sessions),
            'week' => $this->formatForWeekView($sessions),
            'month' => $this->formatForMonthView($sessions),
            default => $sessions,
        };
    }

    /**
     * Format sessions for day view
     */
    protected function formatForDayView(array $sessions): array
    {
        $formatted = [];

        foreach ($sessions as $date => $dateSessions) {
            $formatted[$date] = [
                'date' => $date,
                'formatted_date' => Carbon::parse($date)->format('l, F j, Y'),
                'sessions' => collect($dateSessions)->sortBy('start_time')->values()->toArray(),
                'session_count' => count($dateSessions),
                'total_hours' => $this->calculateDayHours($dateSessions),
            ];
        }

        return $formatted;
    }

    /**
     * Format sessions for week view
     */
    protected function formatForWeekView(array $sessions): array
    {
        $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $formatted = [];

        foreach ($weekDays as $day) {
            $dayDate = Carbon::parse($this->resource['period']['start_date'])
                ->startOfWeek()
                ->addDays(array_search($day, $weekDays))
                ->format('Y-m-d');

            $daySessions = $sessions[$dayDate] ?? [];

            $formatted[$day] = [
                'day' => $day,
                'date' => $dayDate,
                'formatted_date' => Carbon::parse($dayDate)->format('M j'),
                'sessions' => collect($daySessions)->sortBy('start_time')->values()->toArray(),
                'session_count' => count($daySessions),
                'is_today' => $dayDate === now()->format('Y-m-d'),
                'is_weekend' => in_array($day, ['Saturday', 'Sunday']),
            ];
        }

        return $formatted;
    }

    /**
     * Format sessions for month view
     */
    protected function formatForMonthView(array $sessions): array
    {
        $formatted = [];

        foreach ($sessions as $date => $dateSessions) {
            $carbonDate = Carbon::parse($date);

            $formatted[$date] = [
                'date' => $date,
                'day_of_month' => $carbonDate->day,
                'day_name' => $carbonDate->format('D'),
                'session_count' => count($dateSessions),
                'has_sessions' => ! empty($dateSessions),
                'is_today' => $date === now()->format('Y-m-d'),
                'is_weekend' => $carbonDate->isWeekend(),
                'sessions_summary' => $this->getSessionsSummary($dateSessions),
            ];
        }

        return $formatted;
    }

    /**
     * Get formatted period string
     */
    protected function getFormattedPeriod(): string
    {
        $start = Carbon::parse($this->resource['period']['start_date']);
        $end = Carbon::parse($this->resource['period']['end_date']);
        $view = $this->resource['period']['view'];

        return match ($view) {
            'day' => $start->format('l, F j, Y'),
            'week' => $start->format('M j').' - '.$end->format('M j, Y'),
            'month' => $start->format('F Y'),
            default => $start->format('M j').' - '.$end->format('M j, Y'),
        };
    }

    /**
     * Calculate average sessions per day
     */
    protected function getAverageSessionsPerDay(): float
    {
        $totalSessions = $this->resource['summary']['total_sessions'];
        $periodDays = $this->resource['summary']['period_days'];

        return $periodDays > 0 ? round($totalSessions / $periodDays, 1) : 0;
    }

    /**
     * Get the busiest day
     */
    protected function getBusiestDay(): ?array
    {
        $sessions = $this->resource['sessions'];
        $maxSessions = 0;
        $busiestDay = null;

        foreach ($sessions as $date => $dateSessions) {
            $sessionCount = count($dateSessions);
            if ($sessionCount > $maxSessions) {
                $maxSessions = $sessionCount;
                $busiestDay = [
                    'date' => $date,
                    'day_name' => Carbon::parse($date)->format('l'),
                    'session_count' => $sessionCount,
                ];
            }
        }

        return $busiestDay;
    }

    /**
     * Get conflict severity
     */
    protected function getConflictSeverity(array $conflict): string
    {
        return match ($conflict['type']) {
            'time_overlap' => 'high',
            'room_conflict' => 'medium',
            'back_to_back' => 'low',
            default => 'medium',
        };
    }

    /**
     * Calculate total hours for a day
     */
    protected function calculateDayHours(array $sessions): float
    {
        $totalMinutes = 0;

        foreach ($sessions as $session) {
            $start = Carbon::createFromFormat('H:i', $session['start_time']);
            $end = Carbon::createFromFormat('H:i', $session['end_time']);
            $totalMinutes += $start->diffInMinutes($end);
        }

        return round($totalMinutes / 60, 1);
    }

    /**
     * Get sessions summary for month view
     */
    protected function getSessionsSummary(array $sessions): array
    {
        if (empty($sessions)) {
            return [];
        }

        $summary = [];
        $sessionTypes = [];

        foreach ($sessions as $session) {
            $sessionTypes[] = $session['session_type'];
        }

        $typeCounts = array_count_values($sessionTypes);

        foreach ($typeCounts as $type => $count) {
            $summary[] = [
                'type' => $type,
                'count' => $count,
                'label' => ucfirst($type).($count > 1 ? 's' : ''),
            ];
        }

        return $summary;
    }

    /**
     * Generate insights based on timetable data
     */
    protected function generateInsights(): array
    {
        $insights = [];
        $summary = $this->resource['summary'];
        $conflicts = $this->resource['conflicts'];

        // Teaching load insights
        if ($summary['total_teaching_hours'] > 40) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'workload',
                'message' => 'Heavy teaching load detected. Consider workload balance.',
                'value' => $summary['total_teaching_hours'].' hours',
            ];
        } elseif ($summary['total_teaching_hours'] < 10) {
            $insights[] = [
                'type' => 'info',
                'category' => 'workload',
                'message' => 'Light teaching schedule this period.',
                'value' => $summary['total_teaching_hours'].' hours',
            ];
        }

        // Conflict insights
        if (! empty($conflicts)) {
            $insights[] = [
                'type' => 'error',
                'category' => 'scheduling',
                'message' => 'Schedule conflicts detected. Review and resolve.',
                'value' => count($conflicts).' conflict(s)',
            ];
        }

        // Session distribution insights
        $avgSessionsPerDay = $this->getAverageSessionsPerDay();
        if ($avgSessionsPerDay > 5) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'distribution',
                'message' => 'High session density. Consider spreading sessions.',
                'value' => $avgSessionsPerDay.' sessions/day',
            ];
        }

        return $insights;
    }

    /**
     * Get navigation information
     */
    protected function getNavigationInfo(): array
    {
        $start = Carbon::parse($this->resource['period']['start_date']);
        $end = Carbon::parse($this->resource['period']['end_date']);
        $view = $this->resource['period']['view'];

        $previous = match ($view) {
            'day' => [
                'start_date' => $start->copy()->subDay()->format('Y-m-d'),
                'end_date' => $start->copy()->subDay()->format('Y-m-d'),
            ],
            'week' => [
                'start_date' => $start->copy()->subWeek()->format('Y-m-d'),
                'end_date' => $end->copy()->subWeek()->format('Y-m-d'),
            ],
            'month' => [
                'start_date' => $start->copy()->subMonth()->startOfMonth()->format('Y-m-d'),
                'end_date' => $start->copy()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
            default => [
                'start_date' => $start->copy()->subWeek()->format('Y-m-d'),
                'end_date' => $end->copy()->subWeek()->format('Y-m-d'),
            ],
        };

        $next = match ($view) {
            'day' => [
                'start_date' => $start->copy()->addDay()->format('Y-m-d'),
                'end_date' => $start->copy()->addDay()->format('Y-m-d'),
            ],
            'week' => [
                'start_date' => $start->copy()->addWeek()->format('Y-m-d'),
                'end_date' => $end->copy()->addWeek()->format('Y-m-d'),
            ],
            'month' => [
                'start_date' => $start->copy()->addMonth()->startOfMonth()->format('Y-m-d'),
                'end_date' => $start->copy()->addMonth()->endOfMonth()->format('Y-m-d'),
            ],
            default => [
                'start_date' => $start->copy()->addWeek()->format('Y-m-d'),
                'end_date' => $end->copy()->addWeek()->format('Y-m-d'),
            ],
        };

        return [
            'previous' => $previous,
            'next' => $next,
            'today' => [
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ],
        ];
    }
}
