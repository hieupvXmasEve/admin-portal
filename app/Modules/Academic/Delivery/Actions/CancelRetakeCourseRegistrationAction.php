<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Actions\RecordAcademicFinanceCancellationHandoffAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancel a retake course registration by entering Finance-Pending Cancellation
 * and writing a durable Academic handoff for the Finance Cancellation Operation.
 * Terminal cancel happens only after Finance completion is consumed.
 */
class CancelRetakeCourseRegistrationAction
{
    /**
     * @param  array{
     *   registration_id: int,
     *   reason: string,
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

                $registration->markFinancePendingCancellation((int) $userId, $data['reason']);
                $registration = $registration->fresh() ?? $registration;
            }

            RecordAcademicFinanceCancellationHandoffAction::run([
                'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
                'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
                'unpaid_void_reason' => 'retake_course_cancelled',
                // Owner rule #4: cancelling a paid retake (before class link)
                // keeps the money as unapplied balance for future fees.
                'paid_void_reason' => 'retake_course_cancelled_paid_keep_for_later',
                'actor_user_id' => $userId === null ? null : (int) $userId,
                'payload' => ['reason' => $data['reason']],
            ]);

            return $registration->fresh() ?? $registration;
        });
    }
}
