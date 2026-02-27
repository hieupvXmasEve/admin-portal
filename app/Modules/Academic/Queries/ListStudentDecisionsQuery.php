<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStudentDecisionsQuery
{
    private const SORTABLE_COLUMNS = [
        'decision_number' => 'student_decisions.decision_number',
        'decision_signer' => 'student_decisions.decision_signer',
        'issued_at' => 'student_decisions.issued_at',
        'expires_at' => 'student_decisions.expires_at',
    ];

    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $sortKey = (string) ($filters['sort'] ?? 'issued_at');
        $sortColumn = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['issued_at'];
        $sortDirection = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $linkedActionsSub = StudentActionLog::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('decision_id', 'student_decisions.id')
            ->when($campusId, fn (Builder $query) => $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId)));

        $linkedStudentsSub = StudentActionLog::query()
            ->selectRaw('COUNT(DISTINCT student_id)')
            ->whereColumn('decision_id', 'student_decisions.id')
            ->when($campusId, fn (Builder $query) => $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId)));

        return StudentDecision::query()
            ->with(['uploadRecord:id,original_name,url,disk,path,context', 'changedBy:id,name'])
            ->select('student_decisions.*')
            ->selectSub($linkedActionsSub, 'linked_actions_count')
            ->selectSub($linkedStudentsSub, 'linked_students_count')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('decision_name', 'like', "%{$search}%")
                        ->orWhere('decision_number', 'like', "%{$search}%")
                        ->orWhere('decision_signer', 'like', "%{$search}%");
                });
            })
            ->when($filters['issued_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($filters['issued_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '<=', $date))
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            )
            ->withQueryString();
    }
}
