<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\StudentActionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetStudentActionHistoryQuery
{
    /**
     * Get the action history for a specific student.
     *
     * @param  int  $studentId  The student ID
     * @param  array  $filters  Optional filters
     * @return LengthAwarePaginator|Collection
     */
    public function handle(int $studentId, array $filters = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $query = StudentActionLog::query()
            ->with([
                'changedBy:id,name,email',
                'fromSemester:id,name,code',
                'returnSemester:id,name,code',
                'intendedIntakeSemester:id,name,code',
                'dropoutSemester:id,name,code',
                'effectiveSemester:id,name,code',
                'fromCampus:id,name,code',
                'toCampus:id,name,code',
                'attachments',
            ])
            ->where('student_id', $studentId)
            // Filter by action type if provided
            ->when($filters['action_type'] ?? null, function (Builder $query, $actionType) {
                $query->where('action_type', $actionType);
            })
            // Sort by newest first
            ->orderBy('created_at', 'desc');

        if ($paginate) {
            return $query
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString();
        }

        return $query->get();
    }

    /**
     * Get latest action for a student.
     */
    public function getLatest(int $studentId): ?StudentActionLog
    {
        return StudentActionLog::query()
            ->with([
                'changedBy:id,name,email',
                'fromSemester:id,name,code',
                'returnSemester:id,name,code',
                'intendedIntakeSemester:id,name,code',
                'dropoutSemester:id,name,code',
                'effectiveSemester:id,name,code',
                'fromCampus:id,name,code',
                'toCampus:id,name,code',
            ])
            ->where('student_id', $studentId)
            ->latest()
            ->first();
    }

    /**
     * Get count of actions by type for a student.
     */
    public function getCountsByType(int $studentId): array
    {
        return StudentActionLog::query()
            ->where('student_id', $studentId)
            ->selectRaw('action_type, COUNT(*) as count')
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();
    }
}
