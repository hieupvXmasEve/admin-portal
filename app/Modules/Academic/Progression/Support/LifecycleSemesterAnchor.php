<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Enums\StudentActionType;
use App\Models\Semester;
use App\Models\StudentActionLog;

/**
 * Resolves the semester a lifecycle action takes effect in.
 *
 * Each action type anchors its semester in a different column — enrolment and
 * defer use from_semester_id, resume uses return_semester_id, dropout uses
 * dropout_semester_id, admission deferral uses intended_intake_semester_id, and
 * everything else uses effective_semester_id. Reports that read those columns
 * directly end up joining a different one per metric and silently disagree with
 * each other, so every reader resolves the anchor through here instead.
 */
class LifecycleSemesterAnchor
{
    /** Relations eager-loaded by callers that resolve anchors in bulk. */
    public const RELATIONS = [
        'fromSemester',
        'returnSemester',
        'intendedIntakeSemester',
        'dropoutSemester',
        'effectiveSemester',
    ];

    public function forAction(?StudentActionLog $action): ?Semester
    {
        if (! $action instanceof StudentActionLog) {
            return null;
        }

        return match ($this->actionTypeValue($action)) {
            StudentActionType::STUDENT_ENROLLMENT_NE->value,
            StudentActionType::STUDENT_MAJOR_ENROLLMENT->value,
            StudentActionType::ACADEMIC_DEFER->value,
            StudentActionType::WAITING_COURSE_OPENING->value => $action->fromSemester,
            StudentActionType::ACADEMIC_RESUME->value => $action->returnSemester,
            StudentActionType::ADMISSION_DEFERRAL->value => $action->intendedIntakeSemester,
            StudentActionType::ACADEMIC_DROPOUT->value => $action->dropoutSemester,
            default => $action->effectiveSemester,
            // Historical rows may leave the type-specific column null, so fall back
            // to the generic anchor rather than dropping the action from a timeline.
        } ?? $action->effectiveSemester;
    }

    public function idForAction(?StudentActionLog $action): ?int
    {
        $semester = $this->forAction($action);

        return $semester?->id === null ? null : (int) $semester->id;
    }

    public function actionTypeValue(?StudentActionLog $action): ?string
    {
        if (! $action instanceof StudentActionLog) {
            return null;
        }

        return $action->action_type instanceof StudentActionType
            ? $action->action_type->value
            : (string) $action->action_type;
    }
}
