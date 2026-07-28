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
    public const CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE = ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE;

    /**
     * @param  array{
     *   attempt_id: int,
     *   reason: string,
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
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
                'paid_void_reason' => 'exam_resit_cancelled_paid_no_refund',
                'actor_user_id' => auth()->id() === null ? null : (int) auth()->id(),
                'payload' => [
                    'reason' => $data['reason'] ?? $attempt->cancellation_reason,
                    'acknowledge_no_refund' => (bool) ($data['acknowledge_no_refund'] ?? false),
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

    /**
     * @param  array{
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
     * }  $data
     */
    private function assertCancellationAcknowledgements(ExamResitAttempt $attempt, array $data): void
    {
        $settlement = app(AcademicObligationSettlement::class);

        $hasPaidEvidence = $settlement->hasExamResitPaidEvidence($attempt);

        if ($hasPaidEvidence && ! (bool) ($data['acknowledge_no_refund'] ?? false)) {
            throw new RuntimeException(
                'Khoản phí đã thanh toán. Vui lòng xác nhận hủy nhưng giữ nguyên phí đã thu và không tạo hoàn phí.'
            );
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
