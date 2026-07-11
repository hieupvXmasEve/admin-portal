<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceCancellationChargeState;
use App\Shared\Contracts\Finance\FinanceCancellationChargeStateReader;
use App\Shared\Contracts\Finance\FinanceCancellationOperationRequestContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cancel an exam-resit (thi lại) operation before the student sits the resit.
 *
 * Academic enters Finance-Pending Cancellation under its own transaction, then
 * requests a durable Finance Cancellation Operation outside that transaction so
 * Finance writes never share a lock with Academic. The source becomes terminal
 * only after Finance completion is consumed.
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

            // Idempotent re-entry while Finance is still working.
            if ($attempt->status === ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION) {
                return $attempt;
            }

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

            return $attempt->fresh() ?? $attempt;
        });

        // Finance request is intentionally outside the Academic transaction
        // (no cross-context transaction / no Academic lock across Finance writes).
        $operation = app(FinanceCancellationOperationRequestContract::class)->request(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
            AcademicFinanceObligationSource::examResitAttemptRef($attempt),
            AcademicFinanceObligationSource::EXAM_RESIT_FEE,
            'exam_resit_cancelled',
            'exam_resit_cancelled_paid_no_refund',
            auth()->id() === null ? null : (int) auth()->id(),
            [
                'reason' => $data['reason'],
                'acknowledge_no_refund' => (bool) ($data['acknowledge_no_refund'] ?? false),
                'legacy_finance_charge_id' => $attempt->finance_charge_id === null
                    ? null
                    : (int) $attempt->finance_charge_id,
            ],
        );

        Log::info('Exam resit cancellation requested', [
            'exam_resit_attempt_id' => $attempt->id,
            'student_id' => $attempt->student_id,
            'finance_cancellation_operation_id' => $operation->operationId,
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
        $state = app(FinanceCancellationChargeStateReader::class)->forCharge(
            $attempt->finance_charge_id === null ? null : (int) $attempt->finance_charge_id,
        );

        $this->assertAcknowledgementsAgainstState($state, $data);
    }

    /**
     * @param  array{
     *   acknowledge_no_refund?: bool,
     *   confirmation?: string|null,
     * }  $data
     */
    private function assertAcknowledgementsAgainstState(FinanceCancellationChargeState $state, array $data): void
    {
        if ($state->requiresNoRefundAcknowledgement && ! (bool) ($data['acknowledge_no_refund'] ?? false)) {
            throw new RuntimeException(
                'Khoản phí đã thanh toán. Vui lòng xác nhận hủy nhưng giữ nguyên phí đã thu và không tạo hoàn phí.'
            );
        }

        if ($state->requiresUnpaidVoidConfirmation
            && ($data['confirmation'] ?? null) !== self::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE) {
            throw new RuntimeException(
                'Vui lòng xác nhận hủy khoản phí đang chờ thu trước khi hủy nguồn.'
            );
        }
    }
}
