<?php

declare(strict_types=1);

namespace App\Actions\Lecture;

use App\Models\ClassSession;
use App\Models\Semester;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetTeachingHoursAction
{
    /**
     * Normalize report filters so the Inertia page and Excel export use the same
     * default active-semester behavior.
     *
     * @param array{
     *    semester_id?: string|int|null,
     *    search?: string|null,
     *    date_from?: string|null,
     *    date_to?: string|null,
     *    sort?: string|null,
     *    direction?: string|null,
     *    per_page?: int|string|null,
     * } $filters
     * @return array{
     *    semester_id: string,
     *    search: string,
     *    date_from: string,
     *    date_to: string,
     *    sort: string,
     *    direction: string,
     *    per_page: int,
     * }
     */
    public function normalizeFilters(array $filters): array
    {
        $activeSemester = Semester::getActiveSemester();
        $semesterId = $filters['semester_id'] ?? null;

        if ($semesterId === null || $semesterId === '') {
            $semesterId = $activeSemester?->id ?? 'all';
        }

        return [
            'semester_id' => (string) $semesterId,
            'search' => (string) ($filters['search'] ?? ''),
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
            'sort' => in_array(($filters['sort'] ?? 'name'), ['name', 'hours', 'employee_id', 'type'], true)
                ? (string) ($filters['sort'] ?? 'name')
                : 'name',
            'direction' => ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            'per_page' => max(1, min(100, (int) ($filters['per_page'] ?? 15))),
        ];
    }

    /**
     * Get teaching hours report.
     *
     * @param array{
     *    semester_id: string,
     *    search: string,
     *    date_from: string,
     *    date_to: string,
     *    sort: string,
     *    direction: string,
     *    per_page: int,
     * } $filters
     */
    public function execute(array $filters, int $campusId): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters, $campusId);
        $results = $query->paginate($filters['per_page'])->withQueryString();

        return $results->through(fn ($item): array => $this->mapRow($item));
    }

    /**
     * Get all teaching hour rows for export.
     *
     * @param array{
     *    semester_id: string,
     *    search: string,
     *    date_from: string,
     *    date_to: string,
     *    sort: string,
     *    direction: string,
     *    per_page: int,
     * } $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getForExport(array $filters, int $campusId): Collection
    {
        return $this->buildQuery($filters, $campusId)
            ->get()
            ->map(fn ($item): array => $this->mapRow($item));
    }

    /**
     * @param array{
     *    semester_id: string,
     *    search: string,
     *    date_from: string,
     *    date_to: string,
     *    sort: string,
     *    direction: string,
     *    per_page: int,
     * } $filters
     */
    private function buildQuery(array $filters, int $campusId): Builder
    {
        $query = ClassSession::query()
            ->select([
                'lectures.id as lecture_id',
                'lectures.employee_id',
                'lectures.first_name',
                'lectures.last_name',
                'lectures.email',
                'lectures.employment_type',
                DB::raw('COUNT(class_sessions.id) as session_count'),
                DB::raw('COUNT(DISTINCT class_sessions.course_offering_id) as course_count'),
                DB::raw("GROUP_CONCAT(DISTINCT units.code ORDER BY units.code SEPARATOR ', ') as teaching_subjects"),
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT(units.code, IF(course_offerings.section_code IS NULL OR course_offerings.section_code = '', '', CONCAT(' (', course_offerings.section_code, ')'))) ORDER BY units.code, course_offerings.section_code SEPARATOR ', ') as course_list"),
                DB::raw('SUM(COALESCE(class_sessions.duration_minutes, 
                    TIME_TO_SEC(TIMEDIFF(class_sessions.end_time, class_sessions.start_time)) / 60
                )) as total_minutes'),
            ])
            ->join('lectures', 'class_sessions.lecture_id', '=', 'lectures.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('lectures.campus_id', $campusId)
            ->where('course_offerings.campus_id', $campusId)
            ->whereNotNull('class_sessions.lecture_id');

        if ($filters['semester_id'] !== 'all') {
            $query->where('course_offerings.semester_id', $filters['semester_id']);
        }

        if ($filters['date_from'] !== '') {
            $query->where('class_sessions.session_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->where('class_sessions.session_date', '<=', $filters['date_to']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('lectures.first_name', 'like', "%{$search}%")
                    ->orWhere('lectures.last_name', 'like', "%{$search}%")
                    ->orWhere('lectures.employee_id', 'like', "%{$search}%")
                    ->orWhere('lectures.email', 'like', "%{$search}%")
                    ->orWhere('units.code', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(lectures.first_name, ' ', lectures.last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        $query->groupBy(
            'lectures.id',
            'lectures.employee_id',
            'lectures.first_name',
            'lectures.last_name',
            'lectures.email',
            'lectures.employment_type',
        );

        $direction = $filters['direction'];

        if ($filters['sort'] === 'hours') {
            $query->orderBy('total_minutes', $direction);
        } elseif ($filters['sort'] === 'employee_id') {
            $query->orderBy('lectures.employee_id', $direction);
        } elseif ($filters['sort'] === 'type') {
            $query->orderBy('lectures.employment_type', $direction);
        } else {
            $query->orderBy('lectures.last_name', $direction)
                ->orderBy('lectures.first_name', $direction);
        }

        return $query->orderBy('lectures.id');
    }

    /**
     * @param  mixed  $item
     * @return array<string, mixed>
     */
    private function mapRow($item): array
    {
        $totalMinutes = (int) $item->total_minutes;
        $email = (string) $item->email;

        return [
            'lecture_id' => (int) $item->lecture_id,
            'lecture_name' => trim($item->first_name.' '.$item->last_name),
            'lecture_email' => $email,
            'email_account' => explode('@', $email, 2)[0],
            'employee_id' => $item->employee_id,
            'employment_type' => $item->employment_type,
            'employment_type_label' => $this->formatEmploymentType((string) $item->employment_type),
            'course_list' => $item->course_list ?? '',
            'teaching_subjects' => $item->teaching_subjects ?? '',
            'total_hours' => round($totalMinutes / 60, 2),
            'total_minutes' => $totalMinutes,
            'session_count' => (int) $item->session_count,
            'course_count' => (int) $item->course_count,
        ];
    }

    private function formatEmploymentType(string $type): string
    {
        return ucfirst(str_replace('_', ' ', $type));
    }
}
