<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Attendance;
use App\Models\CanvasCourseMapping;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Operational-state read model for the Course Offering Cockpit (ADR 0013).
 *
 * Derives the offering's lifecycle stage, readiness blockers, and available
 * actions from existing data (course_status, sessions, attendance, Canvas
 * mapping). Nothing is persisted; the frontend renders this contract instead
 * of inferring state. Blocker rules mirror MarkCourseOfferingCompletedAction
 * so the UI never promises a completion the backend would reject.
 */
class GetCourseOfferingOperationalStateQuery
{
    public const BLOCKER_SESSIONS_MISSING_ATTENDANCE = 'sessions_missing_attendance';

    public const BLOCKER_SESSIONS_AUTO_ATTENDANCE_ONLY = 'sessions_auto_attendance_only';

    public const BLOCKER_CANVAS_UNSYNCED = 'canvas_unsynced';

    public const ACTION_FINALIZE = 'finalize';

    public const ACTION_RECALCULATE = 'recalculate';

    public const ACTION_SYNC_GRADES = 'sync_grades';

    public const ATTENDANCE_STATUS_RECORDED = 'recorded';

    public const ATTENDANCE_STATUS_NOT_RECORDED = 'not_recorded';

    public const ATTENDANCE_STATUS_AUTO_SYSTEM_ONLY = 'auto_system_only';

    /**
     * @return array{
     *   lifecycle_stage: string,
     *   session_progress: array{total: int, completed: int},
     *   readiness_blockers: array<int, array{code: string, message: string, references: array<int, array{type: string, id: int, label: string}>}>,
     *   available_actions: array<int, array{action: string, label: string, allowed: bool, blocked_by: array<int, string>}>,
     *   session_attendance_status: array<int, array{session_id: int, status: string}>,
     *   has_mapped_canvas_course: bool
     * }
     */
    public static function handle(CourseOffering $courseOffering, User $user): array
    {
        $sessions = $courseOffering->classSessions()
            ->where('status', '!=', 'cancelled')
            ->with(['attendances' => function ($query) {
                $query->select('id', 'class_session_id', 'recording_method');
            }])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get(['id', 'course_offering_id', 'session_title', 'session_date', 'start_time', 'status']);

        $lifecycleStage = self::deriveLifecycleStage($courseOffering, $sessions);

        // Finalize (non-terminal stages) is blocked by the full readiness set
        // (attendance + Canvas). Recalculate (completed stage) only re-checks
        // the Canvas rule — attendance was already satisfied at the original
        // finalize. Cancelled offerings never expose blockers or actions.
        $readinessBlockers = match (true) {
            $lifecycleStage === 'cancelled' => [],
            $lifecycleStage === 'completed' => array_values(array_filter([self::deriveCanvasBlocker($courseOffering)])),
            default => self::deriveReadinessBlockers($courseOffering, $sessions),
        };

        $hasMappedCanvasCourse = self::hasMappedCanvasCourse($courseOffering);

        return [
            'lifecycle_stage' => $lifecycleStage,
            'session_progress' => [
                'total' => $sessions->count(),
                'completed' => $sessions->where('status', 'completed')->count(),
            ],
            'readiness_blockers' => $readinessBlockers,
            'available_actions' => self::deriveAvailableActions($user, $lifecycleStage, $readinessBlockers, $hasMappedCanvasCourse),
            'session_attendance_status' => self::deriveSessionAttendanceStatus($sessions),
            // Independent of the sync_course_grades permission — Recalculate's
            // embedded Canvas pull (issue 11) is gated by recalculate_course_offering
            // alone, so the frontend needs this signal without relying on the
            // sync_grades action (which additionally requires sync_course_grades).
            'has_mapped_canvas_course' => $hasMappedCanvasCourse,
        ];
    }

    /**
     * Lifecycle is derived, never persisted: setup → registration → teaching
     * → grading → completed / cancelled. Cancelled sessions are ignored.
     *
     * @param  Collection<int, ClassSession>  $sessions
     */
    private static function deriveLifecycleStage(CourseOffering $courseOffering, Collection $sessions): string
    {
        if ($courseOffering->course_status === 'cancelled') {
            return 'cancelled';
        }

        if ($courseOffering->course_status === 'completed') {
            return 'completed';
        }

        if ($sessions->isNotEmpty() && $sessions->every(fn (ClassSession $session): bool => $session->status === 'completed')) {
            return 'grading';
        }

        $hasStarted = $sessions->contains(
            fn (ClassSession $session): bool => in_array($session->status, ['in_progress', 'completed'], true)
        );
        if ($hasStarted) {
            return 'teaching';
        }

        if ($sessions->isNotEmpty() || $courseOffering->current_enrollment > 0) {
            return 'registration';
        }

        return 'setup';
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array{code: string, message: string, references: array<int, array{type: string, id: int, label: string}>}>
     */
    private static function deriveReadinessBlockers(CourseOffering $courseOffering, Collection $sessions): array
    {
        $blockers = [];

        $missingAttendance = $sessions->filter(
            fn (ClassSession $session): bool => self::sessionAttendanceStatus($session) === self::ATTENDANCE_STATUS_NOT_RECORDED
        );
        if ($missingAttendance->isNotEmpty()) {
            $blockers[] = [
                'code' => self::BLOCKER_SESSIONS_MISSING_ATTENDANCE,
                'message' => $missingAttendance->count().' session(s) without recorded attendance',
                'references' => self::sessionReferences($missingAttendance),
            ];
        }

        // auto_system-only attendance means attendance has not been manually finalized yet.
        $autoSystemOnly = $sessions->filter(
            fn (ClassSession $session): bool => self::sessionAttendanceStatus($session) === self::ATTENDANCE_STATUS_AUTO_SYSTEM_ONLY
        );
        if ($autoSystemOnly->isNotEmpty()) {
            $blockers[] = [
                'code' => self::BLOCKER_SESSIONS_AUTO_ATTENDANCE_ONLY,
                'message' => $autoSystemOnly->count().' session(s) have only auto-system attendance and need manual confirmation',
                'references' => self::sessionReferences($autoSystemOnly),
            ];
        }

        $canvasBlocker = self::deriveCanvasBlocker($courseOffering);
        if ($canvasBlocker !== null) {
            $blockers[] = $canvasBlocker;
        }

        return $blockers;
    }

    /**
     * Canvas rule (ADR 0013): a mapped-but-unsynced offering blocks
     * completion — Finalize and Recalculate alike. pending / ignored
     * mappings and unmapped offerings don't.
     *
     * @return array{code: string, message: string, references: array<int, array{type: string, id: int, label: string}>}|null
     */
    private static function deriveCanvasBlocker(CourseOffering $courseOffering): ?array
    {
        if ($courseOffering->is_canvas_synced) {
            return null;
        }

        $mappedMappings = $courseOffering->canvasCourseMappings()
            ->where('sync_status', 'mapped')
            ->get(['id', 'course_offering_id', 'canvas_course_name', 'canvas_course_id']);

        if ($mappedMappings->isEmpty()) {
            return null;
        }

        return [
            'code' => self::BLOCKER_CANVAS_UNSYNCED,
            'message' => 'Canvas-mapped but not synced — sync grades from Canvas before finalizing',
            'references' => $mappedMappings
                ->map(fn (CanvasCourseMapping $mapping): array => [
                    'type' => 'canvas_course_mapping',
                    'id' => $mapping->id,
                    'label' => $mapping->canvas_course_name ?? $mapping->canvas_course_id,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Actions blocked by state are sent with blocked_by codes so the UI can
     * disable and explain; actions the user lacks permission for are omitted
     * entirely. Finalize applies to non-terminal offerings (gated by
     * complete_course_offering); Recalculate applies to already-completed
     * offerings (gated by the stricter recalculate_course_offering). Sync
     * grades (ADR 0014) applies only to non-terminal, Canvas-mapped
     * offerings — post-completion, grades flow only through Recalculate.
     * Cancelled offerings never expose an action.
     *
     * @param  array<int, array{code: string, message: string, references: array<int, array{type: string, id: int, label: string}>}>  $readinessBlockers
     * @return array<int, array{action: string, label: string, allowed: bool, blocked_by: array<int, string>}>
     */
    private static function deriveAvailableActions(User $user, string $lifecycleStage, array $readinessBlockers, bool $hasMappedCanvasCourse): array
    {
        if ($lifecycleStage === 'cancelled') {
            return [];
        }

        $blockerCodes = array_values(array_column($readinessBlockers, 'code'));

        if ($lifecycleStage === 'completed') {
            if (! $user->can('recalculate_course_offering')) {
                return [];
            }

            return [[
                'action' => self::ACTION_RECALCULATE,
                'label' => 'Recalculate course',
                'allowed' => $blockerCodes === [],
                'blocked_by' => $blockerCodes,
            ]];
        }

        $actions = [];

        if ($user->can('complete_course_offering')) {
            $actions[] = [
                'action' => self::ACTION_FINALIZE,
                'label' => 'Finalize course',
                'allowed' => $blockerCodes === [],
                'blocked_by' => $blockerCodes,
            ];
        }

        if ($hasMappedCanvasCourse && $user->can('sync_course_grades')) {
            $actions[] = [
                'action' => self::ACTION_SYNC_GRADES,
                'label' => 'Sync from Canvas',
                'allowed' => true,
                'blocked_by' => [],
            ];
        }

        return $actions;
    }

    private static function hasMappedCanvasCourse(CourseOffering $courseOffering): bool
    {
        return $courseOffering->canvasCourseMappings()
            ->where('sync_status', 'mapped')
            ->exists();
    }

    /**
     * Per-session attendance status for the cockpit sessions view. Derived
     * from the same session/attendance data as the readiness blockers above
     * (via sessionAttendanceStatus()) so the two can never drift apart.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array{session_id: int, status: string}>
     */
    private static function deriveSessionAttendanceStatus(Collection $sessions): array
    {
        return $sessions
            ->map(fn (ClassSession $session): array => [
                'session_id' => $session->id,
                'status' => self::sessionAttendanceStatus($session),
            ])
            ->values()
            ->all();
    }

    /**
     * Single source of truth for a session's attendance readiness: not yet
     * recorded, recorded only via the auto_system method (needs manual
     * confirmation), or recorded.
     */
    private static function sessionAttendanceStatus(ClassSession $session): string
    {
        if ($session->attendances->isEmpty()) {
            return self::ATTENDANCE_STATUS_NOT_RECORDED;
        }

        if ($session->attendances->every(fn (Attendance $attendance): bool => $attendance->recording_method === 'auto_system')) {
            return self::ATTENDANCE_STATUS_AUTO_SYSTEM_ONLY;
        }

        return self::ATTENDANCE_STATUS_RECORDED;
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array{type: string, id: int, label: string}>
     */
    private static function sessionReferences(Collection $sessions): array
    {
        return $sessions
            ->map(fn (ClassSession $session): array => [
                'type' => 'class_session',
                'id' => $session->id,
                'label' => "{$session->session_title} ({$session->formatted_date})",
            ])
            ->values()
            ->all();
    }
}
