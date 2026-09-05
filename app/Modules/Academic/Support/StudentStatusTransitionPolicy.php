<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Enums\StudentActionType;
use App\Modules\Academic\Progression\Actions\TransitionProgramEnrollmentAction;

/**
 * Single source of truth for which Student Actions a staff member may *select*
 * for a student in a given status, on the Lifecycle "Record action" surface.
 *
 * This is the next-possible-action list the UI offers — it replaces the old
 * page's Vue-only `availableActions` computed (which fell back to "show all").
 * It is deliberately a curated subset of what the backend will accept:
 *
 * - The backend remains the authority. {@see TransitionProgramEnrollmentAction}
 *   still fully validates every transition (e.g. ACADEMIC_RESUME is rejected
 *   unless the student is `deferred` or `pending_course_opening`), so an illegal
 *   action can never be created even if the UI were bypassed.
 * - Some transitions the backend records *programmatically* are intentionally
 *   NOT human-selectable here: stage moves (`STUDENT_MAJOR_ENROLLMENT`,
 *   pre-uni ⇄ course) are driven by the EGC progression controls, not this
 *   dropdown — so they are excluded to avoid two ways to do the same thing.
 *
 * Statuses with no curated entry (terminal, or surfaces this tab does not act
 * on) expose no selectable actions rather than the old "show everything"
 * fallback, which is what allowed nonsensical picks like resume-from-course.
 */
class StudentStatusTransitionPolicy
{
    /**
     * The Student Actions a staff member may select for a student in this status.
     *
     * @return array<int, StudentActionType>
     */
    public static function selectableActions(string $status): array
    {
        return match ($status) {
            'pending' => [
                StudentActionType::STUDENT_ENROLLMENT_NE,
                StudentActionType::ADMISSION_DEFERRAL,
                StudentActionType::ACADEMIC_DROPOUT,
            ],
            'intake_pre_uni_gc', 'intake_course' => [
                StudentActionType::WAITING_COURSE_OPENING,
                StudentActionType::ACADEMIC_DEFER,
                StudentActionType::ACADEMIC_DROPOUT,
                StudentActionType::CAMPUS_TRANSFER,
            ],
            'pending_course_opening' => [
                StudentActionType::ACADEMIC_RESUME,
                StudentActionType::ACADEMIC_DEFER,
                StudentActionType::ACADEMIC_DROPOUT,
            ],
            'deferred' => [
                StudentActionType::ACADEMIC_RESUME,
                StudentActionType::ACADEMIC_DEFER,
                StudentActionType::ACADEMIC_DROPOUT,
            ],
            // Terminal statuses and statuses this surface does not act on expose
            // no selectable actions.
            default => [],
        };
    }

    /**
     * The selectable action types as their string enum values (for FE payloads).
     *
     * @return array<int, string>
     */
    public static function selectableActionValues(string $status): array
    {
        return array_map(
            static fn (StudentActionType $type): string => $type->value,
            self::selectableActions($status),
        );
    }
}
