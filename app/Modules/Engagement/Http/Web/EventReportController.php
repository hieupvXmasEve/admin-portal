<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Event;
use App\Modules\Engagement\Queries\EventReportingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EventReportController extends Controller
{
    public function __construct(
        private EventReportingQuery $eventReportService
    ) {}

    /**
     * Display the event reports dashboard
     */
    public function index(Request $request): InertiaResponse
    {
        $campus = Campus::find(session('current_campus_id'));
        if (! $campus) {
            abort(403, 'User must be associated with a campus');
        }

        $filters = $request->only(['date_from', 'date_to', 'status']);

        // Set default date range if not provided
        if (! isset($filters['date_from'])) {
            $filters['date_from'] = now()->subMonths(6)->format('Y-m-d');
        }
        if (! isset($filters['date_to'])) {
            $filters['date_to'] = now()->addMonths(1)->format('Y-m-d');
        }

        $analytics = $this->eventReportService->getEventAnalytics($campus->id, $filters);

        return Inertia::render('Events/EventReports', [
            'analytics' => $analytics,
            'filters' => $filters,
            'campus' => [
                'id' => $campus->id,
                'name' => $campus->name,
            ],
        ]);
    }

    /**
     * Get analytics data via API (for real-time updates)
     */
    public function analytics(Request $request): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus) {
            return response()->json(['error' => 'User must be associated with a campus'], 403);
        }

        $filters = $request->only(['date_from', 'date_to', 'status']);
        $analytics = $this->eventReportService->getEventAnalytics($campus, $filters);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get real-time statistics for a specific event
     */
    public function eventStats(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || $event->campus_id !== $campus) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = $this->eventReportService->getRealTimeEventStats($event->id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Export event data to CSV
     */
    public function exportEvents(Request $request): Response
    {
        $campus = session('current_campus_id');
        if (! $campus) {
            abort(403, 'User must be associated with a campus');
        }

        $filters = $request->only(['date_from', 'date_to', 'status']);
        $csvData = $this->eventReportService->exportEventData($campus, $filters);

        $filename = 'events_report_'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($csvData) {
            $handle = fopen('php://output', 'w');

            foreach ($csvData as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export participant data for a specific event to CSV
     */
    public function exportParticipants(Request $request, Event $event): Response
    {
        $campus = session('current_campus_id');
        if (! $campus || $event->campus_id !== $campus) {
            abort(403, 'Unauthorized');
        }

        $csvData = $this->eventReportService->exportParticipantData($event->id);

        $filename = 'event_'.$event->id.'_participants_'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($csvData) {
            $handle = fopen('php://output', 'w');

            foreach ($csvData as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Get event history with financial impact
     */
    public function eventHistory(Request $request): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus) {
            return response()->json(['error' => 'User must be associated with a campus'], 403);
        }

        $filters = $request->only(['date_from', 'date_to', 'status', 'page', 'per_page']);

        // Set pagination defaults
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 15);
        $offset = ($page - 1) * $perPage;

        $query = Event::forCampus((int) $campus->id)
            ->with(['creator', 'participants']);

        // Apply filters
        if (isset($filters['date_from']) && ! empty($filters['date_from'])) {
            $query->where('start_time', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && ! empty($filters['date_to'])) {
            $query->where('end_time', '<=', $filters['date_to']);
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Get total count for pagination
        $total = $query->count();

        // Get paginated results
        $events = $query->orderBy('start_time', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $eventHistory = $events->map(function ($event) {
            $registrations = $event->getRegisteredCount();
            $completions = $event->getCompletedCount();

            return [
                'id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time->format('Y-m-d H:i'),
                'end_time' => $event->end_time->format('Y-m-d H:i'),
                'location' => $event->location,
                'status' => $event->status,
                'creator' => $event->creator->name ?? 'Unknown',
                'gold_reward_amount' => $event->gold_reward_amount,
                'max_participants' => $event->max_participants,
                'statistics' => [
                    'registered' => $registrations,
                    'checked_in' => $event->getCheckedInCount(),
                    'completed' => $completions,
                    'cancelled' => $event->getCancelledCount(),
                    'success_rate' => $registrations > 0 ? round(($completions / $registrations) * 100, 2) : 0,
                    'total_gold_awarded' => $event->getTotalGoldAwarded(),
                ],
                'financial_impact' => [
                    'potential_gold_cost' => $event->max_participants
                        ? $event->max_participants * $event->gold_reward_amount
                        : $registrations * $event->gold_reward_amount,
                    'actual_gold_cost' => $event->getTotalGoldAwarded(),
                    'cost_efficiency' => $registrations > 0
                        ? round(($event->getTotalGoldAwarded() / ($registrations * $event->gold_reward_amount)) * 100, 2)
                        : 0,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $eventHistory,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
            ],
        ]);
    }
}
