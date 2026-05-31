<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class ExportStudentsQuery
{
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
                ->when($filters['statuses'] ?? [], function (Builder $query, array $statuses) {
                    $query->whereIn('status', $statuses);
                })
                ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                    $query->where('status', $status);
                })
                ->when($filters['intake_semester_ids'] ?? [], function (Builder $query, array $intakeSemesterIds) {
                    $query->whereIn('intake_semester_id', $intakeSemesterIds);
                });
        }

        return $query->orderBy('student_id');
    }
}
