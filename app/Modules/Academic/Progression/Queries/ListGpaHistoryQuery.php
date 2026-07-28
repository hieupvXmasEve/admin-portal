<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\GpaCalculation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListGpaHistoryQuery
{
    public function getBuilder(array $filters): Builder
    {
        return GpaCalculation::query()
            ->with(['student', 'program', 'semester'])
            ->where('is_finalized', true)
            ->when($filters['campus_id'] ?? null, function (Builder $query, $campusId) {
                $query->whereHas('student', function ($q) use ($campusId) {
                    $q->where('campus_id', $campusId);
                });
            })
            ->when($filters['semester_id'] ?? null, function (Builder $query, $semesterId) {
                $query->where('semester_id', $semesterId);
            })
            ->when($filters['program_id'] ?? null, function (Builder $query, $programId) {
                $query->where('program_id', $programId);
            })
            ->when($filters['academic_standing'] ?? null, function (Builder $query, $standing) {
                $query->where('academic_standing', $standing);
            })
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('semester_id', 'desc')
            ->orderBy('cumulative_gpa', 'desc');
    }

    public function handle(array $filters): LengthAwarePaginator
    {
        return $this->getBuilder($filters)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}
