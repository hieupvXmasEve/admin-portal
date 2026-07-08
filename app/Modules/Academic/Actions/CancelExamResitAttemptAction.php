<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationData;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationResult;
use App\Shared\Contracts\Finance\FinanceObligationCancellationContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancel an exam-resit (thi lại) operation before the student sits the resit
 * (ACAD-RET-001 slice 7).
 *
 * Lifecycle rules honored here:
 * - Only requested/approved/scheduled attempts are cancellable; terminal states
 *   are rejected.
 * - Cancellation NEVER consumes an `attempt_number` (only a recorded sitting
 *   does), so the student keeps their attempt allowance.
 * - Fee-side cancellation is delegated to Finance through the obligation
 *   cancellation contract, so Academic only records the source lifecycle.
 * - Paid cancellation is allowed only with explicit no-refund acknowledgement:
 *   Academic cancels the sitting while Finance preserves collected-cash
 *   evidence and clears the collectible obligation.
 */
class CancelExamResitAttemptAction
{
    public const CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE = ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE;

    public function __construct(
        private readonly SendExamResitCancellationNoticeAction $sendCancellationNoticeAction,
    ) {}

    /**
     * @param  array{
     *   attempt_id: int,
     *   reason: string,
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
     * }  $data
     */
    public function run(array $data): ExamResitAttempt
    {
        $attempt = DB::transaction(function () use ($data): ExamResitAttempt {
            $attempt = ExamResitAttempt::query()
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            if (! $attempt->isCancellable()) {
                throw new \RuntimeException(
                    "Không thể hủy thi lại ở trạng thái: {$attempt->status}. Chỉ requested/approved/scheduled mới được hủy."
                );
            }

            $previousSessionId = $attempt->exam_resit_session_id;
            $cancellation = app(FinanceObligationCancellationContract::class)->cancel(
                new FinanceObligationCancellationData(
                    source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                    source_kind: AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                    source_ref: AcademicFinanceObligationSource::examResitAttemptRef($attempt),
                    obligation_type: AcademicFinanceObligationSource::EXAM_RESIT_FEE,
                    unpaid_void_reason: 'exam_resit_cancelled',
                    paid_no_refund_void_reason: 'exam_resit_cancelled_paid_no_refund',
                    require_unpaid_confirmation: true,
                    unpaid_confirmation: (string) ($data['confirmation'] ?? ''),
                    expected_unpaid_confirmation: ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE,
                    require_paid_no_refund_acknowledgement: true,
                    acknowledge_no_refund: (bool) ($data['acknowledge_no_refund'] ?? false),
                    legacy_finance_charge_id: $attempt->finance_charge_id === null ? null : (int) $attempt->finance_charge_id,
                    user_id: auth()->id(),
                )
            );
            $feeDisposition = $this->toExamResitFeeDisposition($cancellation);

            $attempt->update([
                'status' => ExamResitAttempt::STATUS_CANCELLED,
                'exam_resit_session_id' => null,
                'cancellation_reason' => $data['reason'],
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'hq_fee_status' => $cancellation->is_paid ? ExamResitAttempt::HQ_FEE_PAID : ExamResitAttempt::HQ_FEE_CANCELLED,
                'cancellation_fee_disposition' => $feeDisposition,
            ]);

            if ($previousSessionId !== null) {
                $this->refreshSessionCandidateCount($previousSessionId);
            }

            Log::info('Exam resit attempt cancelled', [
                'exam_resit_attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'finance_charge_id' => $cancellation->finance_charge_id,
                'fee_disposition' => $feeDisposition,
                'previous_exam_resit_session_id' => $previousSessionId,
                'cancelled_by_user_id' => auth()->id(),
            ]);

            return $attempt->fresh();
        });

        $this->sendCancellationNotice($attempt);

        return $attempt->fresh();
    }

    private function toExamResitFeeDisposition(FinanceObligationCancellationResult $cancellation): string
    {
        return match ($cancellation->fee_disposition) {
            FinanceObligationCancellationResult::FEE_DISPOSITION_KEPT_PAID_NO_REFUND => ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND,
            FinanceObligationCancellationResult::FEE_DISPOSITION_VOIDED_UNPAID_CHARGE => ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE,
            default => ExamResitAttempt::CANCELLATION_FEE_NO_CHARGE,
        };
    }

    private function refreshSessionCandidateCount(int $sessionId): void
    {
        $count = ExamResitAttempt::query()
            ->where('exam_resit_session_id', $sessionId)
            ->whereIn('status', [
                ExamResitAttempt::STATUS_SCHEDULED,
                ExamResitAttempt::STATUS_COMPLETED,
                ExamResitAttempt::STATUS_NO_SHOW,
            ])
            ->count();

        DB::table('exam_resit_sessions')
            ->where('id', $sessionId)
            ->update(['actual_candidates' => $count]);
    }

    private function sendCancellationNotice(ExamResitAttempt $attempt): void
    {
        try {
            $emailLog = $this->sendCancellationNoticeAction->run($attempt);

            ExamResitAttempt::query()
                ->whereKey($attempt->id)
                ->update([
                    'cancellation_notice_sent_at' => now(),
                    'cancellation_notice_email_log_id' => $emailLog->id,
                    'cancellation_notice_error' => null,
                ]);
        } catch (\Throwable $exception) {
            ExamResitAttempt::query()
                ->whereKey($attempt->id)
                ->update([
                    'cancellation_notice_error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);

            Log::warning('Failed to queue exam resit cancellation notice', [
                'exam_resit_attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
