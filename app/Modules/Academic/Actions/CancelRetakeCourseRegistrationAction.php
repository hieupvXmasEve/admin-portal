<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationData;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationResult;
use App\Shared\Contracts\Finance\FinanceObligationCancellationContract;
use Illuminate\Support\Facades\DB;

class CancelRetakeCourseRegistrationAction
{
    /**
     * Cancel a retake course registration.
     * If at payment_pending, void charge and cancel DNG request.
     *
     * @param  array{
     *   registration_id: int,
     *   reason: string,
     * }  $data
     */
    public static function run(array $data): CourseRetakeRegistration
    {
        return DB::transaction(function () use ($data) {
            $registration = CourseRetakeRegistration::query()
                ->lockForUpdate()
                ->findOrFail($data['registration_id']);
            $userId = auth()->id();

            if (! $registration->isCancellable()) {
                throw new \RuntimeException(
                    "Không thể hủy đăng ký học lại ở trạng thái: {$registration->status}. Chỉ approved hoặc payment_pending mới được hủy."
                );
            }

            $cancellation = app(FinanceObligationCancellationContract::class)->cancel(
                new FinanceObligationCancellationData(
                    source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                    source_kind: AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                    source_ref: AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                    obligation_type: AcademicFinanceObligationSource::RETAKE_FEE,
                    unpaid_void_reason: 'retake_course_cancelled',
                    paid_no_refund_void_reason: 'retake_course_cancelled_paid_no_refund',
                    legacy_finance_charge_id: $registration->finance_charge_id === null ? null : (int) $registration->finance_charge_id,
                    user_id: $userId,
                )
            );

            $registration->cancel(
                $userId,
                $data['reason'],
                $cancellation->fee_disposition === FinanceObligationCancellationResult::FEE_DISPOSITION_KEPT_PAID_NO_REFUND
                    ? CourseRetakeRegistration::HQ_FEE_PAID
                    : null,
            );

            return $registration->fresh();
        });
    }
}
