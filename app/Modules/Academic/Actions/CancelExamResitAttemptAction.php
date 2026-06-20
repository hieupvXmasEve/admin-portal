<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Support\Facades\DB;

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
 * - Paid cancellation is BLOCKED here: payment evidence must be preserved and the
 *   released money handled through the HQ-owned refund/reversal/reallocation flow
 *   (deferred), never silently erased during Academic cancellation.
 */
class CancelExamResitAttemptAction
{
    /**
     * @param  array{
     *   attempt_id: int,
     *   reason: string,
     * }  $data
     */
    public function run(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data): ExamResitAttempt {
            $attempt = ExamResitAttempt::query()
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            if (! $attempt->isCancellable()) {
                throw new \RuntimeException(
                    "Không thể hủy thi lại ở trạng thái: {$attempt->status}. Chỉ requested/approved/scheduled mới được hủy."
                );
            }

            if ($attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_PAID) {
                throw new \RuntimeException(
                    'Thi lại đã thanh toán — cần xử lý hoàn/đảo phí qua HQ trước khi hủy. Bằng chứng thanh toán được giữ nguyên.'
                );
            }

            // Unpaid charge already created: void it and cancel any awaiting DNG
            // request so HQ does not keep collecting on a cancelled source.
            if ($attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_CHARGE_CREATED && $attempt->finance_charge_id) {
                app(VoidFinanceChargeAction::class)->handle(
                    chargeId: $attempt->finance_charge_id,
                    reason: 'exam_resit_cancelled',
                    userId: auth()->id(),
                );

                DngPaymentRequest::query()
                    ->where('student_id', $attempt->student_id)
                    ->where('fee_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
                    ->awaitingPayment()
                    ->update(['status' => DngPaymentRequest::STATUS_CANCELLED]);
            }

            $hqFeeStatus = in_array($attempt->hq_fee_status, [
                ExamResitAttempt::HQ_FEE_PENDING,
                ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
            ], true)
                ? ExamResitAttempt::HQ_FEE_CANCELLED
                : $attempt->hq_fee_status;

            $attempt->update([
                'status' => ExamResitAttempt::STATUS_CANCELLED,
                'cancellation_reason' => $data['reason'],
                'cancelled_at' => now(),
                'hq_fee_status' => $hqFeeStatus,
            ]);

            return $attempt->fresh();
        });
    }
}
