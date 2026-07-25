<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\StudentActionLog;
use App\Shared\Contracts\Academic\DTO\StudentLifecycleActionSummary;
use App\Shared\Contracts\Academic\StudentLifecycleActionReader;

final class EloquentStudentLifecycleActionReader implements StudentLifecycleActionReader
{
    public function latestForStudentIds(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if ($studentIds === []) {
            return [];
        }

        return StudentActionLog::query()
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'action_type', 'created_at', 'notes'])
            ->unique('student_id')
            ->mapWithKeys(static fn (StudentActionLog $action): array => [(int) $action->student_id => new StudentLifecycleActionSummary(
                id: (int) $action->id,
                actionType: $action->action_type->value,
                createdAt: $action->created_at?->toIso8601String(),
                notes: $action->notes,
            )])
            ->all();
    }
}
