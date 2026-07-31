<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Queries;

use App\Models\Student;
use App\Shared\Contracts\Academic\StudentLifecycleMatcher;
use Illuminate\Database\Eloquent\Builder;

class ExportStudentsQuery
{
    public function __construct(private readonly StudentLifecycleMatcher $lifecycleMatcher) {}

    public function getBuilder(int $campusId, array $filters): Builder
    {
        $query = Student::query()
            ->where('campus_id', $campusId);

        if (($filters['scope'] ?? 'all') === 'filtered') {
            $query
                ->when($filters['student_ids'] ?? [], function (Builder $query, array $studentIds) {
                    $query->whereIn('student_id', $studentIds);
                })
                ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                    $query->where(function (Builder $builder) use ($search) {
                        $builder->where('student_id', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($filters['program_ids'] ?? [], function (Builder $query, array $programIds) {
                    $query->whereIn('program_id', $programIds);
                })
                ->when($filters['program_id'] ?? null, function (Builder $query, int $programId) {
                    $query->where('program_id', $programId);
                })
                ->when($filters['specialization_ids'] ?? [], function (Builder $query, array $specializationIds) {
                    $query->whereIn('specialization_id', $specializationIds);
                })
                ->when($filters['intake_semester_ids'] ?? [], function (Builder $query, array $intakeSemesterIds) {
                    $query->whereIn('intake_semester_id', $intakeSemesterIds);
                });

            $statuses = ! empty($filters['statuses'])
                ? array_values($filters['statuses'])
                : (! empty($filters['status']) ? [(string) $filters['status']] : []);

            if ($statuses !== []) {
                $studentIds = (clone $query)->pluck('id')->all();
                $query->whereIn('id', $this->lifecycleMatcher->matchingStudentIds($studentIds, $statuses));
            }
        }

        return $query->orderBy('student_id');
    }
}
