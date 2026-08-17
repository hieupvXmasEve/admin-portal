<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Enums\StudentActionType;
use App\Models\StudentActionLog;
use App\Shared\Contracts\Academic\DTO\StudentDeferActionSummary;
use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentDeferLifecycleReader implements StudentDeferLifecycleReader
{
    public function __construct(
        private readonly StudentLifecycleStatusReader $lifecycleStatuses,
    ) {}

    public function findDeferAction(int $actionId): ?StudentDeferActionSummary
    {
        $action = $this->query()
            ->with('student:id,student_id,full_name,campus_id,status')
            ->find($actionId);

        if ($action === null) {
            return null;
        }

        $status = $action->student === null
            ? null
            : $this->lifecycleStatuses->statusesFor([(int) $action->student->id])[(int) $action->student->id] ?? null;

        return $this->summary($action, $status);
    }

    public function listDeferActions(?int $semesterId = null, ?int $campusId = null): array
    {
        $actions = $this->query()
            ->with('student:id,student_id,full_name,campus_id,status')
            ->when($semesterId !== null, fn (Builder $query) => $query->where('from_semester_id', $semesterId))
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $studentIds = $actions
            ->filter(fn (StudentActionLog $action): bool => $action->student !== null)
            ->map(fn (StudentActionLog $action): int => (int) $action->student->id)
            ->unique()
            ->values()
            ->all();
        $statuses = $studentIds === [] ? [] : $this->lifecycleStatuses->statusesFor($studentIds);

        return $actions
            ->map(fn (StudentActionLog $action): StudentDeferActionSummary => $this->summary(
                $action,
                $action->student === null ? null : ($statuses[(int) $action->student->id] ?? null),
            ))
            ->all();
    }

    /** @return Builder<StudentActionLog> */
    private function query(): Builder
    {
        return StudentActionLog::query()
            ->without('changedBy')
            ->where('action_type', StudentActionType::ACADEMIC_DEFER->value);
    }

    private function summary(StudentActionLog $action, ?string $resolvedStatus): StudentDeferActionSummary
    {
        return new StudentDeferActionSummary(
            id: (int) $action->id,
            studentId: (int) $action->student_id,
            studentCode: $action->student?->student_id,
            studentName: $action->student?->full_name,
            campusId: $action->student?->campus_id === null ? null : (int) $action->student->campus_id,
            currentlyDeferred: ($resolvedStatus ?? $action->student?->status) === 'deferred',
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
