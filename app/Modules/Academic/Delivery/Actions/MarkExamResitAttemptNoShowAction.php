<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamResitAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Mark a scheduled exam-resit attempt as a no-show (vắng thi).
 *
 * Owner rule #8: a no-show counts as a FAILED sitting — it consumes an
 * attempt (single definition: `attempt_number` set via the shared
 * next-attempt-number helper), keeps the academic record unchanged (the
 * student keeps their original failing grade), and opens the retake lane.
 * The fee is forfeited (owner decision, validation session 1): cancelling
 * BEFORE the sitting keeps the money for later, missing the sitting loses it.
 * Idempotent: only `scheduled` attempts can transition; re-invoking throws
 * instead of consuming another attempt.
 */
class MarkExamResitAttemptNoShowAction
{
    /**
     * @param  array{
     *   attempt_id: int,
     *   reason?: string|null,
     * }  $data
     */
    public static function run(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data): ExamResitAttempt {
            /** @var ExamResitAttempt $attempt */
            $attempt = ExamResitAttempt::query()
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            if ($attempt->status === ExamResitAttempt::STATUS_NO_SHOW) {
                return $attempt; // idempotent no-op
            }

            if ($attempt->status !== ExamResitAttempt::STATUS_SCHEDULED) {
                throw new RuntimeException(
                    "Chỉ ghi nhận vắng thi cho đơn ở trạng thái scheduled, hiện tại: {$attempt->status}."
                );
            }

            $consumedAttemptNumber = ExamResitAttempt::nextAttemptNumberFor((int) $attempt->academic_record_id);

            $attempt->update([
                'status' => ExamResitAttempt::STATUS_NO_SHOW,
                'no_show_at' => now(),
                'attempt_number' => $consumedAttemptNumber,
                'result_snapshot' => [
                    'outcome' => 'no_show',
                    'marked_by_user_id' => auth()->id(),
                    'marked_at' => now()->toISOString(),
                    'reason' => $data['reason'] ?? null,
                    'fee_outcome' => 'forfeit',
                ],
            ]);

            Log::info('Exam resit marked no-show', [
                'exam_resit_attempt_id' => $attempt->id,
                'academic_record_id' => $attempt->academic_record_id,
                'attempt_number' => $consumedAttemptNumber,
                'marked_by_user_id' => auth()->id(),
                'reason' => $data['reason'] ?? null,
            ]);

            return $attempt->fresh();
        });
    }
}
