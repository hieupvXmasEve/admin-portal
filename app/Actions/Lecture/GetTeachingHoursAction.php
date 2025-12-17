<?php

declare(strict_types=1);

namespace App\Actions\Lecture;

use App\Models\ClassSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetTeachingHoursAction
{
    /**
     * Get teaching hours report.
     *
     * @param array{
     *    semester_id?: string|null,
     *    search?: string|null,
     *    date_from?: string|null,
     *    date_to?: string|null,
     *    sort?: string|null,
     *    direction?: string|null,
     *    per_page?: int|null,
     * } $filters
     * @param int $campusId
     * @return LengthAwarePaginator
     */
    public function execute(array $filters, int $campusId): LengthAwarePaginator
    {
        // Set default date_from to 2025-01-01 if not provided
        $dateFrom = $filters['date_from'] ?? '2025-01-01';
        $dateTo = $filters['date_to'] ?? now()->format('Y-m-d');

        // Ensure date_to is end of day (23:59:59)
        $dateToEndOfDay = Carbon::parse($dateTo)->endOfDay();

        // Build base query with joins
        $query = ClassSession::query()
            ->select([
                'lectures.id as lecture_id',
                'lectures.first_name',
                'lectures.last_name',
                'lectures.email',
                DB::raw('COUNT(class_sessions.id) as session_count'),
                DB::raw('SUM(COALESCE(class_sessions.duration_minutes, 
                    TIME_TO_SEC(TIMEDIFF(class_sessions.end_time, class_sessions.start_time)) / 60
                )) as total_minutes'),
            ])
            ->join('lectures', 'class_sessions.lecture_id', '=', 'lectures.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->where('lectures.campus_id', $campusId)
            ->whereNotNull('class_sessions.lecture_id')
            ->where('class_sessions.session_date', '>=', $dateFrom)
            ->where('class_sessions.session_date', '<=', $dateToEndOfDay->format('Y-m-d'))
            ->groupBy('lectures.id', 'lectures.first_name', 'lectures.last_name', 'lectures.email');

        // Apply semester filter (skip if 'all' or empty)
        if (isset($filters['semester_id']) && $filters['semester_id'] !== 'all') {
            $query->where('course_offerings.semester_id', $filters['semester_id']);
        }

        // Apply search filter (name or email)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('lectures.first_name', 'like', "%{$search}%")
                    ->orWhere('lectures.last_name', 'like', "%{$search}%")
                    ->orWhere('lectures.email', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(lectures.first_name, ' ', lectures.last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        if ($sort === 'name') {
            $query->orderBy('lectures.last_name', $direction)
                ->orderBy('lectures.first_name', $direction);
        } elseif ($sort === 'hours') {
            $query->orderBy('total_minutes', $direction);
        }

        // Get per_page or default to 15
        $perPage = $filters['per_page'] ?? 15;

        // Execute query and paginate
        $results = $query->paginate((int) $perPage)->withQueryString();

        // Transform results to include total_hours
        $results->through(function ($item) {
            $totalMinutes = (int) $item->total_minutes;
            $totalHours = round($totalMinutes / 60, 2);

            return [
                'lecture_id' => $item->lecture_id,
                'lecture_name' => trim($item->first_name . ' ' . $item->last_name),
                'lecture_email' => $item->email,
                'total_hours' => $totalHours,
                'total_minutes' => $totalMinutes,
                'session_count' => (int) $item->session_count,
            ];
        });

        return $results;
    }
}
