<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Actions\RecordAcademicFinanceCancellationHandoffAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancel a retake course registration by entering Finance-Pending Cancellation
 * and writing a durable Academic handoff for the Finance Cancellation Operation.
 * Terminal cancel happens only after Finance completion is consumed.
 */
class CancelRetakeCourseRegistrationAction
{
    public const FEE_OUTCOME_FORFEIT = 'forfeit';

    public const FEE_OUTCOME_KEEP_FOR_LATER = 'keep_for_later';

    public const PAID_VOID_REASON_FORFEIT = 'retake_course_cancelled_paid_no_refund';

    public const PAID_VOID_REASON_KEEP_FOR_LATER = 'retake_course_cancelled_paid_keep_for_later';

    /**
     * @param  array{
     *   registration_id: int,
     *   reason: string,
     *   fee_outcome?: string,
     *   acknowledge_no_refund?: bool,
     * }  $data
     */
    public static function run(array $data): CourseRetakeRegistration
    {
        return DB::transaction(function () use ($data): CourseRetakeRegistration {
            $registration = CourseRetakeRegistration::query()
                ->lockForUpdate()
                ->findOrFail($data['registration_id']);
            $userId = auth()->id();

            if ($registration->status !== CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION) {
                if (! $registration->isCancellable()) {
                    throw new RuntimeException(
                        "Không thể hủy đăng ký học lại ở trạng thái: {$registration->status}. Chỉ approved, payment_pending hoặc paid (chưa gắn lớp) mới được hủy."
                    );
                }

                $thisAction = new self;
                $thisAction->assertCancellationAcknowledgements($registration, $data);

                $registration->markFinancePendingCancellation((int) $userId, $data['reason']);
                $registration = $registration->fresh() ?? $registration;
            }

            $action = new self;

            RecordAcademicFinanceCancellationHandoffAction::run([
                'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
                'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
                'unpaid_void_reason' => 'retake_course_cancelled',
                'paid_void_reason' => $action->paidVoidReason($data),
                'actor_user_id' => $userId === null ? null : (int) $userId,
                'payload' => [
                    'reason' => $data['reason'],
                    'acknowledge_no_refund' => (bool) ($data['acknowledge_no_refund'] ?? false),
                    'fee_outcome' => $action->feeOutcome($data, required: false),
                ],
            ]);

            return $registration->fresh() ?? $registration;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function feeOutcome(array $data, bool $required): string
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function paidVoidReason(array $data): string
    {
        return $this->feeOutcome($data, required: false) === self::FEE_OUTCOME_KEEP_FOR_LATER
            ? self::PAID_VOID_REASON_KEEP_FOR_LATER
            : self::PAID_VOID_REASON_FORFEIT;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCancellationAcknowledgements(CourseRetakeRegistration $registration, array $data): void
    {
        $settlement = app(AcademicObligationSettlement::class);
        $hasPaidEvidence = $settlement->hasRetakePaidEvidence($registration);

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
    }
}
