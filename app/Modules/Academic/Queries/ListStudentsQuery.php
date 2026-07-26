<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Student;
use App\Modules\Academic\Progression\Queries\FilterStudentsByProgramEnrollmentStatus;
use App\Shared\Contracts\Academic\StudentDirectoryReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStudentsQuery implements StudentDirectoryReader
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters, int $campusId): LengthAwarePaginator
    {
        $query = Student::query()
            ->with(['campus', 'program', 'specialization'])
            ->where('campus_id', $campusId);

        $this->applyStudentCodeFilter($query, $filters);
        $this->applySearch($query, $filters);
        $this->applyAdvancedFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query
            ->paginate(
                perPage: (int) ($filters['per_page'] ?? 10),
                page: (int) ($filters['page'] ?? 1),
            )
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyStudentCodeFilter(Builder $query, array $filters): void
    {
        $studentIds = $filters['student_ids'] ?? [];

        if ($studentIds !== []) {
            $query->whereIn('student_id', $studentIds);
        }
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
                ->where('student_id', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAdvancedFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['program_ids'])) {
            $query->whereIn('program_id', $filters['program_ids']);
        } elseif (! empty($filters['program_id'])) {
            $query->where('program_id', (int) $filters['program_id']);
        }

        if (! empty($filters['specialization_ids'])) {
            $query->whereIn('specialization_id', $filters['specialization_ids']);
        }

        if (! empty($filters['statuses'])) {
            (new FilterStudentsByProgramEnrollmentStatus)->apply($query, array_values($filters['statuses']));
        } elseif (! empty($filters['status'])) {
            (new FilterStudentsByProgramEnrollmentStatus)->apply($query, [(string) $filters['status']]);
        }

        if (! empty($filters['intake_semester_ids'])) {
            $query->whereIn('intake_semester_id', $filters['intake_semester_ids']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySorting(Builder $query, array $filters): void
    {
        if (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['direction'] ?? 'asc');
        }

        $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }
}
