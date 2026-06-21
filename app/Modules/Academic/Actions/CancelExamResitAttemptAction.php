<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Modules\Finance\Actions\BridgePaidDngRequestsForChargeAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
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
 * - Unpaid charge handling mirrors course-retake cancellation: an unpaid
 *   `charge_created` charge is voided and its awaiting exam_resit_fee DNG request
 *   is cancelled, so no stale obligation remains.
 * - Paid cancellation is allowed only with explicit no-refund acknowledgement:
 *   Academic cancels the sitting, keeps DNG/payment evidence, and voids the
 *   local source charge without reallocation so collected cash becomes
 *   unapplied credit instead of a stale collectible fee.
 */
class CancelExamResitAttemptAction
{
    public const CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE = ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE;

    public function __construct(
        private readonly SendExamResitCancellationNoticeAction $sendCancellationNoticeAction,
        private readonly BridgePaidDngRequestsForChargeAction $bridgePaidDngRequestsForChargeAction,
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
                ->with('financeCharge')
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            if (! $attempt->isCancellable()) {
                throw new \RuntimeException(
                    "Không thể hủy thi lại ở trạng thái: {$attempt->status}. Chỉ requested/approved/scheduled mới được hủy."
                );
            }

            $charge = $attempt->financeCharge;
            $hasPaidDng = $this->bridgePaidDngRequestsForChargeAction->hasPaidDngForCharge($charge);
            if ($hasPaidDng && $charge !== null && ! $charge->is_fully_paid) {
                $this->bridgePaidDngRequestsForChargeAction->handle($charge);
                $charge = $charge->fresh();
                $this->assertPaidDngCoveredCharge($charge);
            }

            $isPaid = $this->isPaidAttempt($attempt, $charge) || $hasPaidDng;
            $hasUnpaidCharge = $this->hasUnpaidActiveCharge($attempt, $charge) && ! $isPaid;
            $feeDisposition = $this->feeDisposition($isPaid, $hasUnpaidCharge);
            $previousSessionId = $attempt->exam_resit_session_id;

            if ($isPaid) {
                $this->assertNoRefundAcknowledged((bool) ($data['acknowledge_no_refund'] ?? false));
            }

            if ($isPaid && $charge !== null && $charge->status === FinanceCharge::STATUS_ACTIVE) {
                app(VoidFinanceChargeAction::class)->handle(
                    chargeId: $charge->id,
                    reason: 'exam_resit_cancelled_paid_no_refund',
                    userId: auth()->id(),
                    autoReallocate: false,
                );
                $charge = $charge->fresh();
            }

            if ($hasUnpaidCharge && $charge !== null) {
                $this->assertUnpaidFeeCancellationConfirmed((string) ($data['confirmation'] ?? ''));
                $this->cancelAwaitingDngRequestsForCharge($charge->id);
                app(VoidFinanceChargeAction::class)->handle(
                    chargeId: $charge->id,
                    reason: 'exam_resit_cancelled',
                    userId: auth()->id(),
                    autoReallocate: false,
                );
            }

            $attempt->update([
                'status' => ExamResitAttempt::STATUS_CANCELLED,
                'exam_resit_session_id' => null,
                'cancellation_reason' => $data['reason'],
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'hq_fee_status' => $isPaid ? ExamResitAttempt::HQ_FEE_PAID : ExamResitAttempt::HQ_FEE_CANCELLED,
                'cancellation_fee_disposition' => $feeDisposition,
            ]);

            if ($previousSessionId !== null) {
                $this->refreshSessionCandidateCount($previousSessionId);
            }

            Log::info('Exam resit attempt cancelled', [
                'exam_resit_attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'finance_charge_id' => $attempt->finance_charge_id,
                'fee_disposition' => $feeDisposition,
                'previous_exam_resit_session_id' => $previousSessionId,
                'cancelled_by_user_id' => auth()->id(),
            ]);

            return $attempt->fresh();
        });

        $this->sendCancellationNotice($attempt);

        return $attempt->fresh();
    }

    private function isPaidAttempt(ExamResitAttempt $attempt, ?FinanceCharge $charge): bool
    {
        return $attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_PAID
            || ($charge !== null && $charge->status === FinanceCharge::STATUS_ACTIVE && $charge->is_fully_paid);
    }

    private function hasUnpaidActiveCharge(ExamResitAttempt $attempt, ?FinanceCharge $charge): bool
    {
        return $attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_CHARGE_CREATED
            && $charge !== null
            && $charge->status === FinanceCharge::STATUS_ACTIVE
            && ! $charge->is_fully_paid;
    }

    private function feeDisposition(bool $isPaid, bool $hasUnpaidCharge): string
    {
        if ($isPaid) {
            return ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND;
        }

        if ($hasUnpaidCharge) {
            return ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE;
        }

        return ExamResitAttempt::CANCELLATION_FEE_NO_CHARGE;
    }

    private function assertNoRefundAcknowledged(bool $acknowledged): void
    {
        if (! $acknowledged) {
            throw new \RuntimeException(
                'Thi lại đã thanh toán. Vui lòng xác nhận hủy thi lại nhưng giữ nguyên phí đã thu và không tạo hoàn phí.'
            );
        }
    }

    private function assertUnpaidFeeCancellationConfirmed(string $confirmation): void
    {
        if ($confirmation !== ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE) {
            throw new \RuntimeException(
                'Vui lòng xác nhận hủy khoản phí thi lại đang chờ thu trước khi hủy thi lại.'
            );
        }
    }

    private function assertPaidDngCoveredCharge(?FinanceCharge $charge): void
    {
        if ($charge === null || $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid) {
            throw new \RuntimeException(
                'DNG đã ghi nhận thanh toán phí thi lại nhưng chưa thể phân bổ đủ vào khoản phí nội bộ. Vui lòng kiểm tra liên kết DNG/charge trước khi hủy.'
            );
        }
    }

    private function cancelAwaitingDngRequestsForCharge(int $chargeId): int
    {
        $directIds = DngPaymentRequest::query()
            ->awaitingPayment()
            ->where('finance_charge_id', $chargeId)
            ->pluck('id');

        $pivotIds = DngPaymentRequestCharge::query()
            ->where('finance_charge_id', $chargeId)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->awaitingPayment())
            ->pluck('dng_payment_request_id');

        $requestIds = $directIds
            ->merge($pivotIds)
            ->unique()
            ->values();

        if ($requestIds->isEmpty()) {
            return 0;
        }

        return DngPaymentRequest::query()
            ->whereIn('id', $requestIds)
            ->update(['status' => DngPaymentRequest::STATUS_CANCELLED]);
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
