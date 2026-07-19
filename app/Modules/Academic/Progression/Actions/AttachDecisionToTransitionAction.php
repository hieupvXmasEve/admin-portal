<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\AcademicProgressionEvent;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Backfill an authorizing Decision onto an already-recorded lifecycle transition
 * (ADR-0008).
 *
 * A requires-decision transition can be recorded first and the signed quyết định
 * entered later; this action attaches that Decision to the transition — in either
 * lifecycle stream (status action or EGC progression event) — and ensures the
 * student appears on the Decision's coverage roster. Once attached, the
 * transition drops out of the missing-decision report.
 */
class AttachDecisionToTransitionAction
{
    /**
     * @param  array{student_id: int, source: string, source_id: int, decision_id: int}  $data
     * @return StudentActionLog|AcademicProgressionEvent The transition the decision was attached to.
     *
     * @throws InvalidArgumentException When the source is unknown or the transition does not belong to the student.
     */
    public static function run(array $data): StudentActionLog|AcademicProgressionEvent
    {
        $studentId = (int) $data['student_id'];
        $source = $data['source'];
        $sourceId = (int) $data['source_id'];
        $decisionId = (int) $data['decision_id'];

        if (! in_array($source, ['action', 'progression'], true)) {
            throw new InvalidArgumentException("Unknown transition source: {$source}.");
        }

        return DB::transaction(function () use ($studentId, $source, $sourceId, $decisionId): StudentActionLog|AcademicProgressionEvent {
            $decision = StudentDecision::query()->findOrFail($decisionId);

            $transition = $source === 'action'
                ? StudentActionLog::query()->findOrFail($sourceId)
                : AcademicProgressionEvent::query()->findOrFail($sourceId);

            if ((int) $transition->student_id !== $studentId) {
                throw new InvalidArgumentException('The transition does not belong to the given student.');
            }

            $transition->update(['decision_id' => $decision->id]);

            // Keep the roster consistent with the events the decision authorizes.
            $decision->cover($studentId);

            return $transition;
        });
    }
}
