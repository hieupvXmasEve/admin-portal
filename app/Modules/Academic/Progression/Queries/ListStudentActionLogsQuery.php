<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Enums\StudentActionType;
use App\Models\StudentActionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStudentActionLogsQuery
{
    public function getBuilder(array $filters): Builder
    {
        return StudentActionLog::query()
            ->with([
                'student:id,student_id,full_name,email,campus_id',
                'student.campus:id,name,code',
                'changedBy:id,name,email',
                'fromSemester:id,name,code',
                'returnSemester:id,name,code',
                'intendedIntakeSemester:id,name,code',
                'dropoutSemester:id,name,code',
                'effectiveSemester:id,name,code',
                'fromCampus:id,name,code',
                'toCampus:id,name,code',
                'attachments',
                'decision:id,decision_name,decision_number',
            ])
            // Filter by action type
            ->when($filters['action_type'] ?? null, function (Builder $query, $actionType) {
                $query->where('action_type', $actionType);
            })
            // Filter by date range (created_at / changed_at)
            ->when($filters['date_from'] ?? null, function (Builder $query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            // Filter by signed_at date range
            ->when($filters['signed_date_from'] ?? null, function (Builder $query, $signedFrom) {
                $query->whereDate('signed_at', '>=', $signedFrom);
            })
            ->when($filters['signed_date_to'] ?? null, function (Builder $query, $signedTo) {
                $query->whereDate('signed_at', '<=', $signedTo);
            })
            // Filter by semester (any of the semester fields)
            ->when($filters['semester_id'] ?? null, function (Builder $query, $semesterId) {
                $query->where(function ($q) use ($semesterId) {
                    $q->where('from_semester_id', $semesterId)
                        ->orWhere('return_semester_id', $semesterId)
                        ->orWhere('intended_intake_semester_id', $semesterId)
                        ->orWhere('dropout_semester_id', $semesterId)
                        ->orWhere('effective_semester_id', $semesterId);
                });
            })
            // Filter by specific semester field
            ->when($filters['from_semester_id'] ?? null, function (Builder $query, $semesterId) {
                $query->where('from_semester_id', $semesterId);
            })
            ->when($filters['egc_defer_from_block_number'] ?? null, function (Builder $query, $blockNumber) {
                $query->where('egc_defer_from_block_number', (int) $blockNumber);
            })
            ->when($filters['return_semester_id'] ?? null, function (Builder $query, $semesterId) {
                $query->where('return_semester_id', $semesterId);
            })
            ->when($filters['dropout_semester_id'] ?? null, function (Builder $query, $semesterId) {
                $query->where('dropout_semester_id', $semesterId);
            })
            ->when($filters['effective_semester_id'] ?? null, function (Builder $query, $semesterId) {
                $query->where('effective_semester_id', $semesterId);
            })
            // Filter by student's current campus only
            ->when($filters['student_campus_id'] ?? null, function (Builder $query, $campusId) {
                $query->whereHas('student', function (Builder $studentQuery) use ($campusId) {
                    $studentQuery->where('campus_id', $campusId);
                });
            })
            // Filter by campus (student's current campus or transfer campus)
            ->when($filters['campus_id'] ?? null, function (Builder $query, $campusId) {
                $query->where(function ($q) use ($campusId) {
                    $q->whereHas('student', function ($studentQuery) use ($campusId) {
                        $studentQuery->where('campus_id', $campusId);
                    })
                        ->orWhere('to_campus_id', $campusId)
                        ->orWhere('from_campus_id', $campusId);
                });
            })
            // Filter by to_campus specifically
            ->when($filters['to_campus_id'] ?? null, function (Builder $query, $campusId) {
                $query->where('to_campus_id', $campusId);
            })
            // Filter by from_campus specifically
            ->when($filters['from_campus_id'] ?? null, function (Builder $query, $campusId) {
                $query->where('from_campus_id', $campusId);
            })
            // Filter by actor (changed_by_user_id)
            ->when($filters['actor_id'] ?? null, function (Builder $query, $actorId) {
                $query->where('changed_by_user_id', $actorId);
            })
            // Filter by missing documents
            ->when(isset($filters['missing_documents']), function (Builder $query) use ($filters) {
                $query->where('missing_documents', filter_var($filters['missing_documents'], FILTER_VALIDATE_BOOLEAN));
            })
            // Search by student name/id
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            // Sorting
            ->when($filters['sort'] ?? null, function (Builder $query, $sort) use ($filters) {
                $direction = $filters['direction'] ?? 'desc';
                $query->orderBy($sort, $direction);
            }, function (Builder $query) {
                // Default sort: newest first
                $query->orderBy('created_at', 'desc');
            });
    }

    public function handle(array $filters): LengthAwarePaginator
    {
        return $this->getBuilder($filters)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Get action type options for filter dropdown.
     */
    public static function getActionTypeOptions(): array
    {
        return StudentActionType::options();
    }
}
