<?php

declare(strict_types=1);

namespace App\Queries\Lecture;

use App\Models\Lecture;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListLecturesQuery
{
    private const DEFAULT_SORT = 'full_name';

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters, int $campusId): LengthAwarePaginator
    {
        $query = Lecture::query()
            ->with(['campus'])
            ->where('lectures.campus_id', $campusId);

        $this->applySearch($query, $filters);
        $this->applyEmploymentFilters($query, $filters);
        $this->applyTeachingAssignmentFilters($query, $filters, $campusId);
        $this->applySorting($query, $filters);

        return $query
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySearch(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('lectures.employee_id', 'like', "%{$search}%")
                ->orWhere('lectures.first_name', 'like', "%{$search}%")
                ->orWhere('lectures.last_name', 'like', "%{$search}%")
                ->orWhere('lectures.email', 'like', "%{$search}%")
                ->orWhere('lectures.department', 'like', "%{$search}%")
                ->orWhere('lectures.specialization', 'like', "%{$search}%");
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyEmploymentFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['campus_id']) && $filters['campus_id'] !== 'all') {
            $query->where('lectures.campus_id', (int) $filters['campus_id']);
        }

        if (! empty($filters['employment_status']) && $filters['employment_status'] !== 'all') {
            $query->where('lectures.employment_status', $filters['employment_status']);
        }

        if (! empty($filters['employment_type']) && $filters['employment_type'] !== 'all') {
            $query->where('lectures.employment_type', $filters['employment_type']);
        }

        if (! empty($filters['department']) && $filters['department'] !== 'all') {
            $query->where('lectures.department', $filters['department']);
        }

        if (array_key_exists('available_for_assignment', $filters) && $filters['available_for_assignment'] !== null) {
            $query->where('lectures.is_available_for_assignment', (bool) $filters['available_for_assignment']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyTeachingAssignmentFilters(Builder $query, array $filters, int $campusId): void
    {
        $semesterId = $filters['semester_id'] ?? null;
        $unitType = $filters['unit_type'] ?? null;
        $hasSemesterFilter = $semesterId !== null && $semesterId !== '' && $semesterId !== 'all';
        $hasUnitTypeFilter = $unitType !== null && $unitType !== '' && $unitType !== 'all';

        if (! $hasSemesterFilter && ! $hasUnitTypeFilter) {
            return;
        }

        // A course offering can be taught by different lecturers per session
        // (course_offerings.lecture_id is only a default/primary slot), so the
        // "who's actually teaching this semester" answer has to come from
        // class_sessions.lecture_id, not the course_offering-level column.
        $query->whereHas('classSessions', function (Builder $sessionQuery) use ($campusId, $hasSemesterFilter, $semesterId, $hasUnitTypeFilter, $unitType): void {
            $sessionQuery->whereHas('courseOffering', function (Builder $courseOfferingQuery) use ($campusId, $hasSemesterFilter, $semesterId, $hasUnitTypeFilter, $unitType): void {
                $courseOfferingQuery->where('course_offerings.campus_id', $campusId);

                if ($hasSemesterFilter) {
                    $courseOfferingQuery->where('course_offerings.semester_id', (int) $semesterId);
                }

                if ($hasUnitTypeFilter) {
                    $courseOfferingQuery->whereHas('unit', function (Builder $unitQuery) use ($unitType): void {
                        $unitQuery->where('units.unit_type', $unitType);
                    });
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? self::DEFAULT_SORT);
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'employee_id' => $query->orderBy('lectures.employee_id', $direction),
            'academic_rank' => $query->orderBy('lectures.academic_rank', $direction),
            'employment_status' => $query->orderBy('lectures.employment_status', $direction),
            'employment_type' => $query->orderBy('lectures.employment_type', $direction),
            'is_available_for_assignment' => $query->orderBy('lectures.is_available_for_assignment', $direction),
            default => $query
                ->orderBy('lectures.last_name', $direction)
                ->orderBy('lectures.first_name', $direction),
        };

        $query->orderBy('lectures.id', $direction);
    }
}
