<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetStudentDecisionDetailQuery
{
    public function linkedActions(StudentDecision $decision, int $perPage = 10, ?int $campusId = null): LengthAwarePaginator
    {
        return StudentActionLog::query()
            ->with([
                'student:id,student_id,full_name,campus_id',
                'student.campus:id,name,code',
                'changedBy:id,name',
                'decision:id,decision_name,decision_number',
            ])
            ->where('decision_id', $decision->id)
            ->when($campusId, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)))
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'linked_page')
            ->withQueryString();
    }
}
