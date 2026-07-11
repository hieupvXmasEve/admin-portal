<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\FinanceCancellationOperationRequestContract;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancel a retake course registration by entering Finance-Pending Cancellation
 * and requesting a durable Finance Cancellation Operation. Terminal cancel
 * happens only after Finance completion is consumed.
 *
 * Academic and Finance do not share a DB transaction.
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
        $registration = DB::transaction(function () use ($data): CourseRetakeRegistration {
            $registration = CourseRetakeRegistration::query()
                ->lockForUpdate()
                ->findOrFail($data['registration_id']);
            $userId = auth()->id();

            if ($registration->status === CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION) {
                return $registration;
            }

            if (! $registration->isCancellable()) {
                throw new RuntimeException(
                    "Không thể hủy đăng ký học lại ở trạng thái: {$registration->status}. Chỉ approved hoặc payment_pending mới được hủy."
                );
            }

            $registration->markFinancePendingCancellation((int) $userId, $data['reason']);

            return $registration->fresh() ?? $registration;
        });

        app(FinanceCancellationOperationRequestContract::class)->request(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
            AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
            AcademicFinanceObligationSource::RETAKE_FEE,
            'retake_course_cancelled',
            'retake_course_cancelled_paid_no_refund',
            auth()->id() === null ? null : (int) auth()->id(),
            [
                'reason' => $data['reason'],
                'legacy_finance_charge_id' => $registration->finance_charge_id === null
                    ? null
                    : (int) $registration->finance_charge_id,
            ],
        );

        return $registration->fresh() ?? $registration;
    }
}
