<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Modules\Academic\Queries\ListCourseOfferingModuleOptionsQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ListCourseOfferingCockpitQuery
{
    public function __construct(
        private readonly ListCourseOfferingModuleOptionsQuery $moduleOptionsQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters, int $campusId, ?int $semesterId): array
    {
        $query = DB::table('course_offerings')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_offerings.campus_id', $campusId)
            ->whereNotNull('units.id')
            ->whereNull('course_offerings.deleted_at')
            ->select('course_offerings.*');

        $this->applyFilters($query, $filters, $campusId, $semesterId);

        $sortMap = [
            'unit_code' => 'units.code',
            'unit_name' => 'units.name',
            'unit_level' => 'units.level',
            'unit_type' => 'units.unit_type',
            'delivery_mode' => 'course_offerings.delivery_mode',
            'course_status' => 'course_offerings.course_status',
            'enrollment_status' => 'course_offerings.enrollment_status',
            'current_enrollment' => 'course_offerings.current_enrollment',
        ];
        $sort = (string) ($filters['sort'] ?? 'unit_code');
        $direction = (string) ($filters['direction'] ?? 'asc');

        $courseOfferings = $query
            ->orderBy($sortMap[$sort], $direction)
            ->orderBy('course_offerings.id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $this->hydrateRows($courseOfferings);

        return [
            'courseOfferings' => $courseOfferings,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'module_id' => $filters['module_id'] ?? 'all',
                'enrollment_status' => $filters['enrollment_status'] ?? 'all',
                'course_status' => $filters['course_status'] ?? 'all',
                'delivery_mode' => $filters['delivery_mode'] ?? 'all',
                'unit_level' => $filters['unit_level'] ?? 'all',
                'unit_type' => $filters['unit_type'] ?? 'all',
                'page' => (int) ($filters['page'] ?? 1),
                'per_page' => (int) ($filters['per_page'] ?? 15),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statistics' => $this->statistics($campusId, $semesterId),
            'moduleOptions' => $this->moduleOptionsQuery->handle($campusId, $semesterId),
            'unitLevels' => $this->unitLevels($campusId),
            'unitTypes' => $this->unitTypes($campusId),
            'enrollmentStatusOptions' => [
                ['value' => 'open', 'label' => 'Open'],
                ['value' => 'closed', 'label' => 'Closed'],
                ['value' => 'waitlist_only', 'label' => 'Waitlist Only'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'courseStatusOptions' => [
                ['value' => 'not_started', 'label' => 'Not Started'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'deliveryModeOptions' => [
                ['value' => 'in_person', 'label' => 'In Person'],
                ['value' => 'online', 'label' => 'Online'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
                ['value' => 'blended', 'label' => 'Blended'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, int $campusId, ?int $semesterId): void
    {
        if ($semesterId !== null) {
            $query->where('course_offerings.semester_id', $semesterId);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('course_offerings.section_code', 'like', "%{$search}%")
                    ->orWhere('course_offerings.location', 'like', "%{$search}%")
                    ->orWhere('units.code', 'like', "%{$search}%")
                    ->orWhere('units.name', 'like', "%{$search}%");
            });
        }

        $moduleId = $filters['module_id'] ?? null;
        if ($moduleId !== null && $moduleId !== '' && $moduleId !== 'all') {
            $query->whereExists(function ($moduleQuery) use ($moduleId, $campusId): void {
                $moduleQuery
                    ->selectRaw('1')
                    ->from('module_units')
                    ->join('modules', 'modules.id', '=', 'module_units.module_id')
                    ->whereColumn('module_units.unit_id', 'course_offerings.unit_id')
                    ->where('module_units.module_id', (int) $moduleId)
                    ->where('modules.campus_id', $campusId)
                    ->whereNull('modules.deleted_at');
            });
        }

        foreach (['enrollment_status', 'course_status', 'delivery_mode'] as $filter) {
            if (! empty($filters[$filter]) && $filters[$filter] !== 'all') {
                $query->where("course_offerings.{$filter}", $filters[$filter]);
            }
        }

        if (! empty($filters['unit_level']) && $filters['unit_level'] !== 'all') {
            $query->where('units.level', $filters['unit_level']);
        }

        if (! empty($filters['unit_type']) && $filters['unit_type'] !== 'all') {
            $query->where('units.unit_type', $filters['unit_type']);
        }
    }

    private function statistics(int $campusId, ?int $semesterId): array
    {
        $query = DB::table('course_offerings')
            ->where('campus_id', $campusId)
            ->whereNull('deleted_at')
            ->when($semesterId !== null, fn (Builder $builder) => $builder->where('semester_id', $semesterId));

        $totalCapacity = (int) (clone $query)->sum('max_capacity');
        $totalEnrollment = (int) (clone $query)->sum('current_enrollment');

        return [
            'total_offerings' => (clone $query)->count(),
            'active_offerings' => (clone $query)->where('is_active', true)->where('enrollment_status', 'open')->count(),
            'full_offerings' => (clone $query)->whereColumn('current_enrollment', '>=', 'max_capacity')->count(),
            'cancelled_offerings' => (clone $query)->where('enrollment_status', 'cancelled')->count(),
            'total_enrollment' => $totalEnrollment,
            'total_capacity' => $totalCapacity,
            'enrollment_rate' => $totalCapacity > 0 ? round(($totalEnrollment / $totalCapacity) * 100, 2) : 0,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function unitLevels(int $campusId): array
    {
        return DB::table('units')
            ->join('course_offerings', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_offerings.campus_id', $campusId)
            ->whereNotNull('units.level')
            ->distinct()
            ->orderBy('units.level')
            ->pluck('units.level')
            ->map(fn (mixed $level): array => ['value' => (string) $level, 'label' => "Level {$level}"])
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function unitTypes(int $campusId): array
    {
        $labels = [
            'general' => 'General',
            'egc' => 'English Global Citizen',
            'semi' => 'Semiconductor',
            'ai' => 'Artificial Intelligence',
            'mkt' => 'Marketing',
            'ba' => 'Business Administration',
            'cs' => 'Computer Science',
            'ee' => 'Electrical Engineering',
            'me' => 'Mechanical Engineering',
            'fin' => 'Finance',
        ];

        return DB::table('units')
            ->join('course_offerings', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_offerings.campus_id', $campusId)
            ->whereNotNull('units.unit_type')
            ->distinct()
            ->orderBy('units.unit_type')
            ->pluck('units.unit_type')
            ->map(fn (string $type): array => ['value' => $type, 'label' => $labels[$type] ?? ucfirst($type)])
            ->all();
    }

    private function hydrateRows(LengthAwarePaginator $paginator): void
    {
        $rows = $paginator->getCollection();
        if ($rows->isEmpty()) {
            return;
        }

        $unitIds = $rows->pluck('unit_id')->filter()->unique()->values();
        $semesterIds = $rows->pluck('semester_id')->filter()->unique()->values();
        $lectureIds = $rows->pluck('lecture_id')->filter()->unique()->values();

        $units = DB::table('units')->whereIn('id', $unitIds)->get([
            'id', 'code', 'name', 'credit_points', 'level', 'unit_type',
        ])->keyBy('id');
        $modules = DB::table('module_units')
            ->join('modules', 'modules.id', '=', 'module_units.module_id')
            ->whereIn('module_units.unit_id', $unitIds)
            ->whereNull('modules.deleted_at')
            ->orderBy('module_units.order')
            ->get([
                'module_units.unit_id', 'modules.id', 'modules.code', 'modules.name',
                'modules.grading_type', 'modules.total_credits', 'module_units.order',
            ])
            ->groupBy('unit_id');
        $semesters = DB::table('semesters')->whereIn('id', $semesterIds)->get([
            'id', 'name', 'code', 'start_date', 'end_date',
        ])->keyBy('id');
        $lectures = DB::table('lectures')->whereIn('id', $lectureIds)->get([
            'id', 'first_name', 'last_name', 'email', 'academic_rank',
        ])->keyBy('id');

        $rows->transform(function (object $row) use ($units, $modules, $semesters, $lectures): object {
            $unit = $units->get($row->unit_id);
            if ($unit !== null) {
                $unit->modules = $modules->get($row->unit_id, collect())->values()->map(function (object $module): object {
                    unset($module->unit_id, $module->order);

                    return $module;
                });
            }

            $row->unit = $unit;
            $row->semester = $semesters->get($row->semester_id);
            $row->lecture = $lectures->get($row->lecture_id);
            if ($row->lecture !== null) {
                $row->lecture->display_name = trim($row->lecture->first_name.' '.$row->lecture->last_name);
            }
            $row->course_code = $unit?->code;
            $row->course_title = $unit?->name;
            $row->credit_points = $unit?->credit_points === null ? null : (int) $unit->credit_points;
            $row->status = $row->enrollment_status ?? 'open';
            $row->max_enrollment = (int) ($row->max_capacity ?? 0);

            return $row;
        });
    }
}
