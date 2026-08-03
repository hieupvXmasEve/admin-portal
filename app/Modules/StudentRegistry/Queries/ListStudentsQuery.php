<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Queries;

use App\Models\Student;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentLifecycleMatcher;
use App\Shared\Contracts\StudentRegistry\StudentDirectoryReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListStudentsQuery implements StudentDirectoryReader
{
    public function __construct(
        private readonly StudentLifecycleMatcher $lifecycleMatcher,
        private readonly ProgramEnrollmentReader $enrollmentReader,
    ) {}

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

        $paginator = $query
            ->paginate(
                perPage: (int) ($filters['per_page'] ?? 10),
                page: (int) ($filters['page'] ?? 1),
            )
            ->withQueryString();

        $this->overrideStatusWithProgramEnrollmentTruth($paginator->getCollection());

        return $paginator;
    }

    /**
     * `students.status` is a legacy column that program-enrollment transitions
     * (e.g. intake_pre_uni_gc -> intake_course) don't write back to. Override
     * it with the live program_enrollments projection so the directory list
     * matches the academic-summary page instead of the stale column.
     *
     * @param  Collection<int, Student>  $students
     */
    private function overrideStatusWithProgramEnrollmentTruth(Collection $students): void
    {
        $studentIds = $students->pluck('id')->map(static fn (int|string $id): int => (int) $id)->all();
        if ($studentIds === []) {
            return;
        }

        $summaries = $this->enrollmentReader->forStudentIds($studentIds);

        $students->each(function (Student $student) use ($summaries): void {
            if (isset($summaries[$student->id])) {
                $student->status = $summaries[$student->id]->legacyCompatibleStatus();
            }
        });
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

        $statuses = ! empty($filters['statuses'])
            ? array_values($filters['statuses'])
            : (! empty($filters['status']) ? [(string) $filters['status']] : []);

        if ($statuses !== []) {
            $studentIds = (clone $query)->pluck('id')->all();
            $query->whereIn('id', $this->lifecycleMatcher->matchingStudentIds($studentIds, $statuses));
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
