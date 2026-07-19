<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UnlinkStudentFromDecisionAction
{
    /** @param array{decision_id: int, action_log_id: int, user_id: int, campus_id?: int|null} $data */
    public static function run(array $data): array
    {
        $decision = StudentDecision::query()->findOrFail($data['decision_id']);
        $actionLog = StudentActionLog::query()->findOrFail($data['action_log_id']);
        $userId = $data['user_id'];
        $campusId = $data['campus_id'] ?? null;

        if ($actionLog->decision_id !== $decision->id) {
            Log::info('Student decision unlink skipped (not linked to this decision)', [
                'decision_id' => $decision->id,
                'action_log_id' => $actionLog->id,
                'current_decision_id' => $actionLog->decision_id,
                'changed_by' => $userId,
            ]);

            return [
                'decision_id' => $decision->id,
                'unlinked_action_count' => 0,
                'action_log_id' => $actionLog->id,
            ];
        }

        $unlinkedCount = DB::transaction(function () use ($decision, $actionLog, $campusId): int {
            $query = StudentActionLog::query()
                ->whereKey($actionLog->id)
                ->where('decision_id', $decision->id)
                ->where('action_type', $actionLog->action_type);

            if ($campusId !== null) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId));
            }

            return $query->update(['decision_id' => null]);
        });

        Log::info('Student decision unlink completed', [
            'decision_id' => $decision->id,
            'action_log_id' => $actionLog->id,
            'action_type' => $actionLog->action_type,
            'student_id' => $actionLog->student_id,
            'unlinked_action_count' => $unlinkedCount,
            'changed_by' => $userId,
        ]);

        return [
            'decision_id' => $decision->id,
            'unlinked_action_count' => $unlinkedCount,
            'action_log_id' => $actionLog->id,
        ];
    }
}
