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
                ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                    $query->where(function (Builder $builder) use ($search) {
                        $builder->where('student_id', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($filters['program_id'] ?? null, function (Builder $query, int $programId) {
                    $query->where('program_id', $programId);
                })
                ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                    $query->where('status', $status);
                });
        }

        return $query->orderBy('student_id');
    }
}
