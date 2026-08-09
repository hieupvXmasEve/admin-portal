<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Queries;

use App\Models\Campus;
use App\Modules\Engagement\Models\Event;
use App\Modules\Engagement\Models\EventParticipant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class EventReportingQuery
{
    /**
     * Get comprehensive event analytics for a campus
     */
    public function getEventAnalytics(int $campusId, array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : Carbon::now()->subMonths(6);
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : Carbon::now()->addMonths(1);
        $baseQuery = Event::forCampus($campusId)
            ->whereBetween('start_time', [$dateFrom, $dateTo]);

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $baseQuery->where('status', $filters['status']);
        }

        $events = $baseQuery->with(['participants'])->get();

        return [
            'summary' => $this->getEventSummary($events),
            'participation_trends' => $this->getParticipationTrends($events, $dateFrom, $dateTo),
            'gold_distribution' => $this->getGoldDistribution($events),
            'event_success_metrics' => $this->getEventSuccessMetrics($events),
            'status_breakdown' => $this->getStatusBreakdown($events),
            'monthly_trends' => $this->getMonthlyTrends($events, $dateFrom, $dateTo),
            'top_performing_events' => $this->getTopPerformingEvents($events),
            'participation_patterns' => $this->getParticipationPatterns($events),
        ];
    }

    /**
     * Get event summary statistics
     */
    protected function getEventSummary(Collection $events): array
    {
        $totalEvents = $events->count();
        $totalParticipants = $events->sum(fn ($event) => $event->participants->count());
        $totalGoldAwarded = $events->sum(fn ($event) => $event->getTotalGoldAwarded());
        $averageParticipation = $totalEvents > 0 ? $totalParticipants / $totalEvents : 0;

        return [
            'total_events' => $totalEvents,
            'total_participants' => $totalParticipants,
            'total_gold_awarded' => $totalGoldAwarded,
            'average_participation_per_event' => round($averageParticipation, 2),
            'published_events' => $events->where('status', 'published')->count(),
            'completed_events' => $events->where('status', 'completed')->count(),
            'cancelled_events' => $events->where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get participation trends over time
     */
    protected function getParticipationTrends(Collection $events, Carbon $dateFrom, Carbon $dateTo): array
    {
        $trends = [];
        $current = $dateFrom->copy()->startOfWeek();

        while ($current->lte($dateTo)) {
            $weekEnd = $current->copy()->endOfWeek();

            $weekEvents = $events->filter(function ($event) use ($current, $weekEnd) {
                return $event->start_time->between($current, $weekEnd);
            });

            $trends[] = [
                'week_start' => $current->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'events_count' => $weekEvents->count(),
                'total_registrations' => $weekEvents->sum(fn ($event) => $event->getRegisteredCount()),
                'total_checkins' => $weekEvents->sum(fn ($event) => $event->getCheckedInCount()),
                'total_completions' => $weekEvents->sum(fn ($event) => $event->getCompletedCount()),
            ];

            $current->addWeek();
        }

        return $trends;
    }

    /**
     * Get gold distribution statistics
     */
    protected function getGoldDistribution(Collection $events): array
    {
        $goldByEvent = $events->map(function ($event) {
            return [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'gold_per_participant' => $event->gold_reward_amount,
                'total_awarded' => $event->getTotalGoldAwarded(),
                'participants_awarded' => $event->participants->where('gold_awarded', true)->count(),
            ];
        })->sortByDesc('total_awarded')->values();

        return [
            'total_gold_distributed' => $events->sum(fn ($event) => $event->getTotalGoldAwarded()),
            'average_gold_per_event' => $events->avg(fn ($event) => $event->getTotalGoldAwarded()),
            'highest_reward_event' => $goldByEvent->first(),
            'gold_by_event' => $goldByEvent->take(10)->toArray(),
        ];
    }

    /**
     * Get event success metrics
     */
    protected function getEventSuccessMetrics(Collection $events): array
    {
        $publishedEvents = $events->where('status', 'published');
        $completedEvents = $events->where('status', 'completed');

        $successMetrics = $completedEvents->map(function ($event) {
            $registrations = $event->getRegisteredCount();
            $checkins = $event->getCheckedInCount();
            $completions = $event->getCompletedCount();

            return [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'start_time' => $event->start_time->format('Y-m-d H:i'),
                'registrations' => $registrations,
                'checkins' => $checkins,
                'completions' => $completions,
                'checkin_rate' => $registrations > 0 ? ($checkins / $registrations) * 100 : 0,
                'completion_rate' => $checkins > 0 ? ($completions / $checkins) * 100 : 0,
                'overall_success_rate' => $registrations > 0 ? ($completions / $registrations) * 100 : 0,
            ];
        });

        return [
            'average_checkin_rate' => $successMetrics->avg('checkin_rate'),
            'average_completion_rate' => $successMetrics->avg('completion_rate'),
            'average_overall_success_rate' => $successMetrics->avg('overall_success_rate'),
            'event_metrics' => $successMetrics->sortByDesc('overall_success_rate')->values()->toArray(),
        ];
    }

    /**
     * Get status breakdown
     */
    protected function getStatusBreakdown(Collection $events): array
    {
        return [
            'draft' => $events->where('status', 'draft')->count(),
            'published' => $events->where('status', 'published')->count(),
            'completed' => $events->where('status', 'completed')->count(),
            'cancelled' => $events->where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get monthly trends
     */
    protected function getMonthlyTrends(Collection $events, Carbon $dateFrom, Carbon $dateTo): array
    {
        $trends = [];
        $current = $dateFrom->copy()->startOfMonth();

        while ($current->lte($dateTo)) {
            $monthEnd = $current->copy()->endOfMonth();

            $monthEvents = $events->filter(function ($event) use ($current, $monthEnd) {
                return $event->start_time->between($current, $monthEnd);
            });

            $trends[] = [
                'month' => $current->format('Y-m'),
                'month_name' => $current->format('F Y'),
                'events_count' => $monthEvents->count(),
                'total_participants' => $monthEvents->sum(fn ($event) => $event->getRegisteredCount()),
                'total_gold_awarded' => $monthEvents->sum(fn ($event) => $event->getTotalGoldAwarded()),
                'completion_rate' => $this->calculateAverageCompletionRate($monthEvents),
            ];

            $current->addMonth();
        }

        return $trends;
    }

    /**
     * Get top performing events
     */
    protected function getTopPerformingEvents(Collection $events): array
    {
        return $events->map(function ($event) {
            $registrations = $event->getRegisteredCount();
            $completions = $event->getCompletedCount();

            return [
                'event_id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time->format('Y-m-d H:i'),
                'status' => $event->status,
                'registrations' => $registrations,
                'completions' => $completions,
                'success_rate' => $registrations > 0 ? ($completions / $registrations) * 100 : 0,
                'gold_awarded' => $event->getTotalGoldAwarded(),
            ];
        })
            ->sortByDesc('success_rate')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get participation patterns
     */
    protected function getParticipationPatterns(Collection $events): array
    {
        $patterns = [
            'by_day_of_week' => [],
            'by_time_of_day' => [],
            'by_duration' => [],
        ];

        // Day of week analysis
        for ($day = 0; $day < 7; $day++) {
            $dayEvents = $events->filter(fn ($event) => $event->start_time->dayOfWeek === $day);
            $patterns['by_day_of_week'][] = [
                'day' => Carbon::create()->dayOfWeek($day)->format('l'),
                'events_count' => $dayEvents->count(),
                'avg_participation' => $dayEvents->avg(fn ($event) => $event->getRegisteredCount()),
                'avg_success_rate' => $this->calculateAverageCompletionRate($dayEvents),
            ];
        }

        // Time of day analysis (morning, afternoon, evening)
        $timeSlots = [
            'morning' => ['start' => 6, 'end' => 12],
            'afternoon' => ['start' => 12, 'end' => 18],
            'evening' => ['start' => 18, 'end' => 24],
        ];

        foreach ($timeSlots as $slot => $times) {
            $slotEvents = $events->filter(function ($event) use ($times) {
                $hour = $event->start_time->hour;

                return $hour >= $times['start'] && $hour < $times['end'];
            });

            $patterns['by_time_of_day'][] = [
                'time_slot' => $slot,
                'events_count' => $slotEvents->count(),
                'avg_participation' => $slotEvents->avg(fn ($event) => $event->getRegisteredCount()),
                'avg_success_rate' => $this->calculateAverageCompletionRate($slotEvents),
            ];
        }

        // Duration analysis
        $durationRanges = [
            '0-1 hours' => ['min' => 0, 'max' => 60],
            '1-2 hours' => ['min' => 60, 'max' => 120],
            '2-4 hours' => ['min' => 120, 'max' => 240],
            '4+ hours' => ['min' => 240, 'max' => PHP_INT_MAX],
        ];

        foreach ($durationRanges as $range => $duration) {
            $rangeEvents = $events->filter(function ($event) use ($duration) {
                $eventDuration = $event->start_time->diffInMinutes($event->end_time);

                return $eventDuration >= $duration['min'] && $eventDuration < $duration['max'];
            });

            $patterns['by_duration'][] = [
                'duration_range' => $range,
                'events_count' => $rangeEvents->count(),
                'avg_participation' => $rangeEvents->avg(fn ($event) => $event->getRegisteredCount()),
                'avg_success_rate' => $this->calculateAverageCompletionRate($rangeEvents),
            ];
        }

        return $patterns;
    }

    /**
     * Calculate average completion rate for a collection of events
     */
    protected function calculateAverageCompletionRate(Collection $events): float
    {
        if ($events->isEmpty()) {
            return 0;
        }

        $rates = $events->map(function ($event) {
            $registrations = $event->getRegisteredCount();
            $completions = $event->getCompletedCount();

            return $registrations > 0 ? ($completions / $registrations) * 100 : 0;
        });

        return $rates->avg();
    }

    /**
     * Export event data to CSV format
     */
    public function exportEventData(int $campusId, array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : Carbon::now()->subMonths(6);
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : Carbon::now();

        $query = Event::forCampus($campusId)
            ->whereBetween('start_time', [$dateFrom, $dateTo])
            ->with(['participants.student', 'creator', 'campus']);

        // Apply status filter if provided
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $events = $query->get();

        $csvData = [];
        $csvData[] = [
            'Event ID',
            'Title',
            'Description',
            'Start Time',
            'End Time',
            'Location',
            'Status',
            'Gold Reward Amount',
            'Max Participants',
            'Total Registered',
            'Total Checked In',
            'Total Completed',
            'Total Cancelled',
            'Total Gold Awarded',
            'Participation Rate (%)',
            'Success Rate (%)',
            'Created By',
            'Created At',
            'Published At',
            'Completed At',
            'Cancelled At',
        ];

        foreach ($events as $event) {
            $registrations = $event->getRegisteredCount();
            $completions = $event->getCompletedCount();

            $csvData[] = [
                $event->id,
                $event->title,
                $event->description,
                $event->start_time->format('Y-m-d H:i:s'),
                $event->end_time->format('Y-m-d H:i:s'),
                $event->location,
                $event->status,
                $event->gold_reward_amount,
                $event->max_participants ?? 'Unlimited',
                $registrations,
                $event->getCheckedInCount(),
                $completions,
                $event->getCancelledCount(),
                $event->getTotalGoldAwarded(),
                round($event->getParticipationRate(), 2),
                $registrations > 0 ? round(($completions / $registrations) * 100, 2) : 0,
                $event->creator->name ?? 'Unknown',
                $event->created_at->format('Y-m-d H:i:s'),
                $event->published_at?->format('Y-m-d H:i:s') ?? '',
                $event->completed_at?->format('Y-m-d H:i:s') ?? '',
                $event->cancelled_at?->format('Y-m-d H:i:s') ?? '',
            ];
        }

        return $csvData;
    }

    /**
     * Export participant data to CSV format
     */
    public function exportParticipantData(int $eventId): array
    {
        $event = Event::with(['participants.student', 'participants.checkinStaff'])->findOrFail($eventId);

        $csvData = [];
        $csvData[] = [
            'Participant ID',
            'Student ID',
            'Student Name',
            'Student Email',
            'Status',
            'Registered At',
            'Check-in Time',
            'Check-in Staff',
            'Gold Awarded',
            'Awarded At',
            'Device Info',
        ];

        foreach ($event->participants as $participant) {
            $csvData[] = [
                $participant->id,
                $participant->student->student_id ?? 'Unknown',
                $participant->student->user->name ?? 'Unknown',
                $participant->student->user->email ?? 'Unknown',
                $participant->status,
                $participant->registered_at?->format('Y-m-d H:i:s') ?? '',
                $participant->checkin_time?->format('Y-m-d H:i:s') ?? '',
                $participant->checkinStaff->name ?? '',
                $participant->gold_awarded ? 'Yes' : 'No',
                $participant->awarded_at?->format('Y-m-d H:i:s') ?? '',
                $participant->checkin_device_info ? json_encode($participant->checkin_device_info) : '',
            ];
        }

        return $csvData;
    }

    /**
     * Get real-time event statistics
     */
    public function getRealTimeEventStats(int $eventId): array
    {
        $event = Event::with(['participants'])->findOrFail($eventId);

        return [
            'event_id' => $event->id,
            'title' => $event->title,
            'status' => $event->status,
            'start_time' => $event->start_time->format('Y-m-d H:i:s'),
            'end_time' => $event->end_time->format('Y-m-d H:i:s'),
            'max_participants' => $event->max_participants,
            'statistics' => [
                'registered' => $event->getRegisteredCount(),
                'checked_in' => $event->getCheckedInCount(),
                'completed' => $event->getCompletedCount(),
                'cancelled' => $event->getCancelledCount(),
                'available_spots' => $event->getAvailableSpots(),
                'participation_rate' => round($event->getParticipationRate(), 2),
                'total_gold_awarded' => $event->getTotalGoldAwarded(),
                'capacity_reached' => $event->hasReachedCapacity(),
            ],
            'status_breakdown' => [
                'registered' => $event->participants->where('status', 'registered')->count(),
                'checked_in' => $event->participants->where('status', 'checked_in')->count(),
                'completed' => $event->participants->where('status', 'completed')->count(),
                'cancelled' => $event->participants->where('status', 'cancelled')->count(),
            ],
            'recent_activity' => $this->getRecentActivity($event),
        ];
    }

    /**
     * Get recent activity for an event
     */
    protected function getRecentActivity(Event $event): array
    {
        return $event->participants()
            ->with(['student.user'])
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($participant) {
                return [
                    'student_name' => $participant->student->user->name ?? 'Unknown',
                    'action' => $this->getActionDescription($participant),
                    'timestamp' => $participant->updated_at->format('Y-m-d H:i:s'),
                    'status' => $participant->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get action description for participant activity
     */
    protected function getActionDescription(EventParticipant $participant): string
    {
        switch ($participant->status) {
            case 'registered':
                return 'Registered for event';
            case 'checked_in':
                return 'Checked into event';
            case 'completed':
                return 'Completed event';
            case 'cancelled':
                return 'Cancelled registration';
            default:
                return 'Status updated';
        }
    }
}
