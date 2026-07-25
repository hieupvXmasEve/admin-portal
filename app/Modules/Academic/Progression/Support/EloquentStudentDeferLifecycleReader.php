<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Enums\StudentActionType;
use App\Models\StudentActionLog;
use App\Shared\Contracts\Academic\DTO\StudentDeferActionSummary;
use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentDeferLifecycleReader implements StudentDeferLifecycleReader
{
    public function findDeferAction(int $actionId): ?StudentDeferActionSummary
    {
        $action = $this->query()
            ->with('student:id,student_id,full_name,campus_id,status')
            ->find($actionId);

        return $action === null ? null : $this->summary($action);
    }

    public function listDeferActions(?int $semesterId = null, ?int $campusId = null): array
    {
        return $this->query()
            ->with('student:id,student_id,full_name,campus_id,status')
            ->when($semesterId !== null, fn (Builder $query) => $query->where('from_semester_id', $semesterId))
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentActionLog $action): StudentDeferActionSummary => $this->summary($action))
            ->all();
    }

    /** @return Builder<StudentActionLog> */
    private function query(): Builder
    {
        return StudentActionLog::query()
            ->without('changedBy')
            ->where('action_type', StudentActionType::ACADEMIC_DEFER->value);
    }

    private function summary(StudentActionLog $action): StudentDeferActionSummary
    {
        return new StudentDeferActionSummary(
            id: (int) $action->id,
            studentId: (int) $action->student_id,
            studentCode: $action->student?->student_id,
            studentName: $action->student?->full_name,
            campusId: $action->student?->campus_id === null ? null : (int) $action->student->campus_id,
            currentlyDeferred: $action->student?->status === 'deferred',
            fromSemesterId: $action->from_semester_id === null ? null : (int) $action->from_semester_id,
            returnSemesterId: $action->return_semester_id === null ? null : (int) $action->return_semester_id,
            effectiveAt: $action->effective_at?->toIso8601String(),
            signedAt: $action->signed_at?->toIso8601String(),
            changedByUserId: $action->changed_by_user_id === null ? null : (int) $action->changed_by_user_id,
            createdAt: $action->created_at?->toIso8601String(),
            notes: $action->notes,
        );
    }
}
