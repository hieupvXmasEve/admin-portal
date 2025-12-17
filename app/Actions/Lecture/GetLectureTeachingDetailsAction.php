<?php

declare(strict_types=1);

namespace App\Actions\Lecture;

use App\Models\ClassSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetLectureTeachingDetailsAction
{
    /**
     * Get detailed teaching hours for a specific lecturer.
     *
     * @param int $lectureId
     * @param array{
     *    course_offering_ids?: array<int>|null,
     *    date_from?: string|null,
     *    date_to?: string|null,
     *    sort?: string|null,
     *    direction?: string|null,
     *    per_page?: int|null,
     * } $filters
     * @return array{
     *     sessions: LengthAwarePaginator,
     *     stats: array{
     *         total_hours: float,
     *         total_minutes: int,
     *         total_sessions: int,
     *         unique_courses: int
     *     }
     * }
     */
    public function execute(int $lectureId, array $filters): array
    {
        // Set default date_from to 2025-01-01 if not provided
        $dateFrom = $filters['date_from'] ?? '2025-01-01';
        $dateTo = $filters['date_to'] ?? now()->format('Y-m-d');
        // Base query
        $query = ClassSession::query()
            ->select([
                'class_sessions.*',
                'course_offering_id' => 'class_sessions.course_offering_id', // Explicit validation of content
                'course_offerings.id as course_offering_id',
                'units.code as unit_code',
                'units.name as unit_name',
                DB::raw('COALESCE(class_sessions.duration_minutes, TIME_TO_SEC(TIMEDIFF(class_sessions.end_time, class_sessions.start_time)) / 60) as calculated_duration')
            ])
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('class_sessions.lecture_id', $lectureId)
            ->where('class_sessions.session_date', '>=', $dateFrom)
            ->where('class_sessions.session_date', '<=', $dateTo);

        // Filter by specific course offerings
        if (!empty($filters['course_offering_ids'])) {
            $query->whereIn('class_sessions.course_offering_id', $filters['course_offering_ids']);
        }

        // Calculate stats before pagination
        // Clone query for stats to avoid messing up the main query
        $statsQuery = clone $query;
        // We need to calculate sum of durations. 
        // Note: We can't use the 'calculated_duration' alias directly in sum() on all generic SQL drivers easily without subquery, 
        // so we repeat the logic or use get(). Since dataset for one lecturer isn't huge, get() + collection sum might be okay, 
        // but DB sum is better for performance.
        
        $statsResult = $statsQuery->select([
             DB::raw('COUNT(class_sessions.id) as total_sessions'),
             DB::raw('COUNT(DISTINCT class_sessions.course_offering_id) as unique_courses'),
             DB::raw('SUM(COALESCE(class_sessions.duration_minutes, TIME_TO_SEC(TIMEDIFF(class_sessions.end_time, class_sessions.start_time)) / 60)) as total_minutes')
        ])->first();

        $totalMinutes = (int) ($statsResult->total_minutes ?? 0);
        
        $stats = [
            'total_hours' => round($totalMinutes / 60, 2),
            'total_minutes' => $totalMinutes,
            'total_sessions' => (int) $statsResult->total_sessions,
            'unique_courses' => (int) $statsResult->unique_courses,
        ];

        // Apply sorting
        $sort = $filters['sort'] ?? 'date';
        $direction = $filters['direction'] ?? 'desc';

        if ($sort === 'date') {
            $query->orderBy('class_sessions.session_date', $direction)
                  ->orderBy('class_sessions.start_time', $direction);
        } elseif ($sort === 'duration') {
            $query->orderBy('calculated_duration', $direction);
        } else {
            // Default sort
             $query->orderBy('class_sessions.session_date', 'desc')
                  ->orderBy('class_sessions.start_time', 'desc');
        }

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $sessions = $query->paginate((int) $perPage)->withQueryString();

        return [
            'sessions' => $sessions,
            'stats' => $stats,
        ];
    }
}
