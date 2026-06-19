<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\AcademicRecord;

/**
 * Pure classifier that decides, at course finalization, whether a student passed
 * and — when they did not — the explicit `failure_reason` that routes them to the
 * correct remediation lane (ACAD-RET-001).
 *
 * Policy (locked decisions):
 * - Grade failure  → `grade_failed`     → eligible for exam resit (`thi lại`).
 * - Attendance failure (or both) → `attendance_failed` / `both_failed`
 *                   → must go through course retake (`học lại`).
 * - Attendance %   = (present + late) / (present + late + absent) × 100.
 *                   Excused and not-recorded sessions are EXCLUDED from the
 *                   denominator (story policy), so they never penalise a student.
 * - Attendance is evaluated only when there is recorded evidence. A course with
 *   no recorded sessions is "not applicable" — grade alone decides (this keeps
 *   the historical grade-only behavior for records without attendance data).
 * - Not-recorded sessions are surfaced as `attendance_evidence_state` so the
 *   downstream resit-eligibility gate can block until evidence is complete; they
 *   do not, by themselves, fail the student here.
 * - `override_pass` always wins: the staff-forced result stands, and an overridden
 *   failure is labelled `manual_failed`.
 *
 * This stays pure (scalars in, array out) so the decision matrix is unit-tested
 * without a database and can never drift from the persisted result.
 */
final class FailureReasonClassifier
{
    public const EVIDENCE_CLEAN = 'clean';

    public const EVIDENCE_NOT_RECORDED = 'not_recorded';

    public const EVIDENCE_NO_SESSIONS = 'no_sessions';

    /**
     * @return array{is_passed: bool, failure_reason: ?string, snapshot: array<string, mixed>}
     */
    public static function classify(
        float $finalPercentage,
        float $gradeThreshold,
        int $present,
        int $late,
        int $absent,
        int $notRecorded,
        int $totalSessions,
        float $attendanceThreshold,
        bool $overridePass,
        bool $overrideIsPassed,
        ?\DateTimeInterface $evaluatedAt = null,
    ): array {
        $evaluatedAt ??= now();
        $recorded = $present + $late + $absent;
        $attendanceEvaluable = $recorded > 0;
        $attendancePct = $attendanceEvaluable
            ? round((($present + $late) / $recorded) * 100, 2)
            : null;

        $evidenceState = match (true) {
            $notRecorded > 0 => self::EVIDENCE_NOT_RECORDED,
            ! $attendanceEvaluable => self::EVIDENCE_NO_SESSIONS,
            default => self::EVIDENCE_CLEAN,
        };

        $gradeFailed = $finalPercentage < $gradeThreshold;
        $attendanceFailed = $attendanceEvaluable && $attendancePct < $attendanceThreshold;

        if ($overridePass) {
            $isPassed = $overrideIsPassed;
            $failureReason = $isPassed ? null : AcademicRecord::FAILURE_MANUAL_FAILED;
        } else {
            $isPassed = ! ($gradeFailed || $attendanceFailed);
            $failureReason = match (true) {
                $isPassed => null,
                $gradeFailed && $attendanceFailed => AcademicRecord::FAILURE_BOTH_FAILED,
                $gradeFailed => AcademicRecord::FAILURE_GRADE_FAILED,
                default => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
            };
        }

        return [
            'is_passed' => $isPassed,
            'failure_reason' => $failureReason,
            'snapshot' => [
                'final_score' => round($finalPercentage, 2),
                'grade_threshold' => $gradeThreshold,
                'attendance_pct' => $attendancePct,
                'attendance_threshold' => $attendanceThreshold,
                'attendance_evidence_state' => $evidenceState,
                'grade_failed' => $gradeFailed,
                'attendance_failed' => $attendanceFailed,
                'override_pass' => $overridePass,
                'evaluated_at' => $evaluatedAt->format(DATE_ATOM),
            ],
        ];
    }
}
