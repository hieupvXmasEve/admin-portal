<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Record an exam-resit (thi lại) sitting result and apply it to the final
 * Academic record (ACAD-RET-001, hard gate: academic_records).
 *
 * Locked policy decisions implemented here:
 * - Higher-score rule: the final academic_records result uses the HIGHER of the
 *   existing final score and the resit score. A lower resit never reduces the
 *   record; it is still recorded and consumes an attempt.
 * - On an applied (strictly higher) resit, the result is score-authoritative: it
 *   clears any prior manual override (`override_pass`), and a still-failing result
 *   is normalized to `grade_failed` (attendance was already fine to be eligible).
 * - Attempt counting: `attempt_number` is consumed ONLY at completion (the student
 *   actually sat the resit and a result was recorded), separate from the
 *   request/source sequence assigned at creation time.
 * - History preservation: the pre-resit result is snapshotted on the attempt
 *   (`previous_result_snapshot`) and appended to `academic_records.grade_history`
 *   under `exam_resit_applications`, so a later Canvas sync / re-finalization can
 *   overwrite the score without erasing the resit audit trail.
 * - Payment gate: completion requires canonical-derived paid state, unless the
 *   syllabus policy allows an unpaid sitting AND a visible reason is recorded.
 * - GPA/progression recalculation is FLAGGED only (requires_gpa_recalc), not run
 *   here, per the story stop-condition that defers grade-engine recalculation.
 */
class CompleteExamResitAttemptAction
{
    private const DEFAULT_GRADE_THRESHOLD = 60.0;

    private const EGC_GRADE_THRESHOLD = 70.0;

    /**
     * @param  array{
     *   attempt_id: int,
     *   resit_score: int|float|string,
     *   resit_grade?: string|null,
     *   sat_at?: string|null,
     *   unpaid_sitting_reason?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function run(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data): ExamResitAttempt {
            $attempt = ExamResitAttempt::query()
                ->with(['unit', 'syllabusTemplate'])
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            $this->assertCanComplete($attempt);

            $record = AcademicRecord::query()
                ->lockForUpdate()
                ->findOrFail($attempt->academic_record_id);

            $resitScore = $this->resolveResitScore($data);
            $unpaidAttributes = $this->resolvePaymentGate($attempt, $data);

            $threshold = $this->resolveGradeThreshold($attempt);
            $resitGrade = $data['resit_grade'] ?? AcademicRecord::calculateLetterGrade($resitScore);
            $resitPassed = $resitScore >= $threshold;

            $originalScore = (float) ($record->final_percentage ?? 0);
            $chosenScore = max($originalScore, $resitScore);
            $applied = $resitScore > $originalScore;

            $previousSnapshot = $this->snapshotRecord($record);
            $consumedAttemptNumber = $this->nextAttemptNumber($attempt);

            $passFlipped = $this->applyResultToRecord(
                $record,
                $attempt,
                $resitScore,
                $resitGrade,
                $resitPassed,
                $chosenScore,
                $applied,
                $threshold,
                $consumedAttemptNumber,
                $previousSnapshot,
            );

            $completedAt = $this->resolveSatAt($data);
            $resultSnapshot = [
                'previous' => $previousSnapshot,
                'resit' => [
                    'score' => round($resitScore, 2),
                    'grade' => $resitGrade,
                    'passed' => $resitPassed,
                ],
                'grade_threshold' => $threshold,
                'final_chosen_score' => round($chosenScore, 2),
                'applied' => $applied,
                'requires_gpa_recalc' => $applied && $passFlipped,
                'unpaid_sitting' => $unpaidAttributes !== [],
                'completed_by_user_id' => auth()->id(),
                'completed_at' => $completedAt->toISOString(),
            ];

            $attempt->update($unpaidAttributes + [
                'status' => ExamResitAttempt::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                'attempt_number' => $consumedAttemptNumber,
                'resit_score' => $resitScore,
                'resit_grade' => $resitGrade,
                'resit_passed' => $resitPassed,
                'previous_result_snapshot' => $previousSnapshot,
                'final_chosen_score' => $chosenScore,
                'result_snapshot' => $resultSnapshot,
                'notes' => $this->mergeNotes($attempt->notes, $data['notes'] ?? null),
            ]);

            Log::info('Exam resit completed', [
                'exam_resit_attempt_id' => $attempt->id,
                'academic_record_id' => $record->id,
                'attempt_number' => $consumedAttemptNumber,
                'previous_final_percentage' => $originalScore,
                'resit_score' => $resitScore,
                'final_chosen_score' => $chosenScore,
                'applied' => $applied,
                'requires_gpa_recalc' => $applied && $passFlipped,
                'completed_by_user_id' => auth()->id(),
            ]);

            return $attempt->fresh();
        });
    }

    private function assertCanComplete(ExamResitAttempt $attempt): void
    {
        // Completion requires a scheduled sitting. The scheduling slice
        // (ScheduleExamResitAttemptAction) now moves an approved attempt to
        // `scheduled` by assigning it to a unit-scoped session, so there is no
        // longer an approved-state bypass.
        if ($attempt->status === ExamResitAttempt::STATUS_SCHEDULED) {
            return;
        }

        $message = match ($attempt->status) {
            ExamResitAttempt::STATUS_COMPLETED => 'Lần thi lại này đã được ghi nhận kết quả.',
            ExamResitAttempt::STATUS_APPROVED => 'Lần thi lại chưa được xếp lịch, chưa thể ghi nhận kết quả.',
            default => 'Trạng thái thi lại không hợp lệ để ghi nhận kết quả.',
        };

        throw ValidationException::withMessages(['status' => [$message]]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveResitScore(array $data): float
    {
        $score = $data['resit_score'] ?? null;

        if (! is_numeric($score)) {
            throw ValidationException::withMessages([
                'resit_score' => ['Điểm thi lại phải là một con số hợp lệ.'],
            ]);
        }

        $score = (float) $score;

        if ($score < 0 || $score > 100) {
            throw ValidationException::withMessages([
                'resit_score' => ['Điểm thi lại phải nằm trong khoảng 0 đến 100.'],
            ]);
        }

        return $score;
    }

    /**
     * Completion requires canonical-derived paid state. An unpaid sitting is only
     * allowed when the snapshotted syllabus policy permits it AND a visible reason
     * is recorded, mirroring the design contract for unpaid-before-pay sittings.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Extra attributes to persist on the attempt.
     */
    private function resolvePaymentGate(ExamResitAttempt $attempt, array $data): array
    {
        if ($attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_PAID) {
            return [];
        }

        if (! (bool) $attempt->allow_unpaid_sitting_snapshot) {
            throw ValidationException::withMessages([
                'payment' => ['Lệ phí thi lại chưa được thanh toán, không thể ghi nhận kết quả.'],
            ]);
        }

        $reason = trim((string) ($data['unpaid_sitting_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'unpaid_sitting_reason' => ['Cần ghi rõ lý do cho phép thi lại khi chưa thanh toán.'],
            ]);
        }

        return [
            'unpaid_allowed_reason' => $reason,
            'unpaid_allowed_by_user_id' => auth()->id(),
            'unpaid_allowed_at' => now(),
        ];
    }

    private function resolveGradeThreshold(ExamResitAttempt $attempt): float
    {
        $syllabusThreshold = $attempt->syllabusTemplate?->min_grade_threshold;

        if ($syllabusThreshold !== null) {
            return (float) $syllabusThreshold;
        }

        return $attempt->unit?->unit_type === 'egc'
            ? self::EGC_GRADE_THRESHOLD
            : self::DEFAULT_GRADE_THRESHOLD;
    }

    /**
     * Consume the next attempt number for the record. Counts only previously
     * consumed attempts (completed sittings), never request/source rows.
     */
    private function nextAttemptNumber(ExamResitAttempt $attempt): int
    {
        $maxConsumed = (int) ExamResitAttempt::query()
            ->where('academic_record_id', $attempt->academic_record_id)
            ->whereNotNull('attempt_number')
            ->max('attempt_number');

        return $maxConsumed + 1;
    }

    /**
     * Apply the higher-score rule to the academic record and append the resit
     * audit entry to grade_history.
     *
     * @param  array<string, mixed>  $previousSnapshot
     * @return bool Whether the record's pass state flipped (drives GPA recalc flag).
     */
    private function applyResultToRecord(
        AcademicRecord $record,
        ExamResitAttempt $attempt,
        float $resitScore,
        ?string $resitGrade,
        bool $resitPassed,
        float $chosenScore,
        bool $applied,
        float $threshold,
        int $consumedAttemptNumber,
        array $previousSnapshot,
    ): bool {
        $wasPassed = (bool) $record->is_passed;

        $gradeHistory = $record->grade_history ?? [];
        $gradeHistory['exam_resit_applications'][] = [
            'exam_resit_attempt_id' => $attempt->id,
            'request_sequence' => $attempt->request_sequence,
            'attempt_number' => $consumedAttemptNumber,
            'previous_final_percentage' => $previousSnapshot['final_percentage'],
            'previous_letter_grade' => $previousSnapshot['final_letter_grade'],
            'previous_completion_status' => $previousSnapshot['completion_status'],
            'previous_is_passed' => $previousSnapshot['is_passed'],
            'resit_score' => round($resitScore, 2),
            'resit_grade' => $resitGrade,
            'resit_passed' => $resitPassed,
            'final_chosen_score' => round($chosenScore, 2),
            'grade_threshold' => $threshold,
            'applied' => $applied,
            'applied_by_user_id' => auth()->id(),
            'applied_at' => now()->toISOString(),
        ];

        if (! $applied) {
            // Higher-score rule: a lower resit never reduces the record. We still
            // persist the audit entry so the (non-applied) attempt is traceable.
            $record->update(['grade_history' => $gradeHistory]);

            return false;
        }

        $isPassed = $chosenScore >= $threshold;
        $creditPoints = (float) $record->credit_points > 0
            ? (float) $record->credit_points
            : (float) $record->credit_hours;

        $record->update([
            'final_percentage' => $chosenScore,
            'final_letter_grade' => AcademicRecord::calculateLetterGrade($chosenScore),
            'grade_points' => AcademicRecord::calculateGradePoints($chosenScore),
            'quality_points' => $chosenScore * $creditPoints,
            'is_passed' => $isPassed,
            'completion_status' => $isPassed ? 'completed' : 'failed',
            'satisfies_prerequisite' => $isPassed,
            'credit_points_earned' => $isPassed ? $creditPoints : 0,
            'credit_hours_earned' => $isPassed ? (float) $record->credit_hours : 0,
            // A recorded resit sitting is score-authoritative and supersedes any
            // prior manual override: clear failure_reason on pass, otherwise the
            // still-failing result is normalized to grade_failed (attendance was
            // already fine to be eligible for resit).
            'failure_reason' => $isPassed ? null : AcademicRecord::FAILURE_GRADE_FAILED,
            'override_pass' => false,
            'grade_history' => $gradeHistory,
            'last_grade_change_at' => now(),
        ]);

        return $isPassed !== $wasPassed;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotRecord(AcademicRecord $record): array
    {
        return [
            'final_percentage' => $record->final_percentage !== null ? (float) $record->final_percentage : null,
            'final_letter_grade' => $record->final_letter_grade,
            'grade_points' => $record->grade_points !== null ? (float) $record->grade_points : null,
            'quality_points' => $record->quality_points !== null ? (float) $record->quality_points : null,
            'is_passed' => (bool) $record->is_passed,
            'completion_status' => $record->completion_status,
            'credit_points_earned' => (float) $record->credit_points_earned,
            'credit_hours_earned' => (float) $record->credit_hours_earned,
            'failure_reason' => $record->failure_reason,
            'grade_finalized_date' => $record->grade_finalized_date?->toDateString(),
            'snapshotted_at' => now()->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSatAt(array $data): Carbon
    {
        $satAt = $data['sat_at'] ?? null;

        return $satAt ? Carbon::parse($satAt) : now();
    }

    private function mergeNotes(?string $existing, ?string $incoming): ?string
    {
        $incoming = $incoming !== null ? trim($incoming) : '';

        if ($incoming === '') {
            return $existing;
        }

        return $existing ? $existing."\n".$incoming : $incoming;
    }
}
