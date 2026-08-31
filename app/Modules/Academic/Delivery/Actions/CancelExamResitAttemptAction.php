<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Actions\RecordAcademicFinanceCancellationHandoffAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cancel an exam-resit (thi lại) operation before the student sits the resit.
 *
 * Academic marks Finance-Pending and writes a durable handoff outbox in the
 * same transaction. Finance Cancellation Operation is requested only via the
 * handoff worker after commit — no cross-context transaction, no lost handoff.
 */
class CancelExamResitAttemptAction
{
    /**
     * Fee outcome chosen by staff when cancelling a PAID resit attempt:
     *  - forfeit: the paid fee is lost (charge stays as revenue);
     *  - keep_for_later: the paid fee is released to unapplied balance.
     */
    public const FEE_OUTCOME_FORFEIT = 'forfeit';

    public const FEE_OUTCOME_KEEP_FOR_LATER = 'keep_for_later';

    public const PAID_VOID_REASON_FORFEIT = 'exam_resit_cancelled_paid_no_refund';

    public const PAID_VOID_REASON_KEEP_FOR_LATER = 'exam_resit_cancelled_paid_keep_for_later';

    public const CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE = ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE;

    /**
     * @param  array{
     *   attempt_id: int,
     *   reason: string,
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
     *   fee_outcome?: string,
     * }  $data
     */
    public static function run(array $data): ExamResitAttempt
    {
        return app(self::class)->handle($data);
    }

    /**
     * @param  array{
     *   attempt_id: int,
     *   reason: string,
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
     *   fee_outcome?: string,
     * }  $data
     */
    public function handle(array $data): ExamResitAttempt
    {
        $attempt = DB::transaction(function () use ($data): ExamResitAttempt {
            $attempt = ExamResitAttempt::query()
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            $alreadyPending = $attempt->status === ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION;

            if (! $alreadyPending) {
                if (! $attempt->isCancellable()) {
                    throw new RuntimeException(
                        "Không thể hủy thi lại ở trạng thái: {$attempt->status}. Chỉ requested/approved/scheduled mới được hủy."
                    );
                }

                $this->assertCancellationAcknowledgements($attempt, $data);

                $attempt->update([
                    'status' => ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION,
                    'cancellation_reason' => $data['reason'],
                    'cancelled_by_user_id' => auth()->id(),
                ]);
                $attempt = $attempt->fresh() ?? $attempt;
            }

            // Same correctness window as pending: durable handoff outbox.
            RecordAcademicFinanceCancellationHandoffAction::run([
                'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
                'source_kind' => AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                'source_ref' => AcademicFinanceObligationSource::examResitAttemptRef($attempt),
                'obligation_type' => AcademicFinanceObligationSource::EXAM_RESIT_FEE,
                'unpaid_void_reason' => 'exam_resit_cancelled',
                'paid_void_reason' => $this->paidVoidReason($data),
                'actor_user_id' => auth()->id() === null ? null : (int) auth()->id(),
                'payload' => [
                    'reason' => $data['reason'] ?? $attempt->cancellation_reason,
                    'acknowledge_no_refund' => (bool) ($data['acknowledge_no_refund'] ?? false),
                    'fee_outcome' => $this->feeOutcome($data),
                ],
            ]);

            return $attempt;
        });

        Log::info('Exam resit cancellation handoff recorded', [
            'exam_resit_attempt_id' => $attempt->id,
            'student_id' => $attempt->student_id,
            'cancelled_by_user_id' => auth()->id(),
        ]);

        return $attempt->fresh() ?? $attempt;
    }

    private function feeOutcome(array $data, bool $required = false): string
    {
        $raw = $data['fee_outcome'] ?? null;
        if ($raw === null || $raw === '') {
            if ($required) {
                throw new RuntimeException(
                    'fee_outcome không hợp lệ. Chỉ chấp nhận "forfeit" (mất phí) hoặc "keep_for_later" (lưu phí dùng sau).'
                );
            }

            return self::FEE_OUTCOME_FORFEIT;
        }

        $outcome = (string) $raw;

        if (! in_array($outcome, [self::FEE_OUTCOME_FORFEIT, self::FEE_OUTCOME_KEEP_FOR_LATER], true)) {
            throw new RuntimeException(
                'fee_outcome không hợp lệ. Chỉ chấp nhận "forfeit" (mất phí) hoặc "keep_for_later" (lưu phí dùng sau).'
            );
        }

        return $outcome;
    }

    private function paidVoidReason(array $data): string
    {
        return $this->feeOutcome($data) === self::FEE_OUTCOME_KEEP_FOR_LATER
            ? self::PAID_VOID_REASON_KEEP_FOR_LATER
            : self::PAID_VOID_REASON_FORFEIT;
    }

    /**
     * @param  array{
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
     *   fee_outcome?: string,
     * }  $data
     */
    private function assertCancellationAcknowledgements(ExamResitAttempt $attempt, array $data): void
    {
        $settlement = app(AcademicObligationSettlement::class);

        $hasPaidEvidence = $settlement->hasExamResitPaidEvidence($attempt);

        if ($hasPaidEvidence) {
            $this->feeOutcome($data, required: true);

            if (! (bool) ($data['acknowledge_no_refund'] ?? false)) {
                throw new RuntimeException(
                    $this->feeOutcome($data, required: true) === self::FEE_OUTCOME_KEEP_FOR_LATER
                        ? 'Khoản phí đã thanh toán. Vui lòng xác nhận hủy và lưu phí đã thu dùng cho phí phát sinh sau.'
                        : 'Khoản phí đã thanh toán. Vui lòng xác nhận hủy nhưng mất phí đã thu (không hoàn phí).'
                );
            }
        }

        if (! $hasPaidEvidence
            && $settlement->hasUnsettledExamResitObligation($attempt)
            && ($data['confirmation'] ?? null) !== self::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE) {
            throw new RuntimeException(
                'Vui lòng xác nhận hủy khoản phí đang chờ thu trước khi hủy nguồn.'
            );
        }
    }
}
