<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
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
            $registration = CourseRetakeRegistration::lockForUpdate()->findOrFail($data['registration_id']);
            $userId = auth()->id();

            if (! $registration->isCancellable()) {
                throw new \RuntimeException(
                    "Không thể hủy đăng ký học lại ở trạng thái: {$registration->status}. Chỉ approved hoặc payment_pending mới được hủy."
                );
            }

            // If payment_pending, void the finance charge and cancel DNG request
            if ($registration->status === CourseRetakeRegistration::STATUS_PAYMENT_PENDING && $registration->finance_charge_id) {
                // Void charge
                app(VoidFinanceChargeAction::class)->handle(
                    chargeId: $registration->finance_charge_id,
                    reason: 'retake_course_cancelled',
                    userId: $userId,
                );

                // Cancel DNG request if pending
                DngPaymentRequest::query()
                    ->where('student_id', $registration->student_id)
                    ->where('fee_type', 'retake_fee')
                    ->awaitingPayment()
                    ->update(['status' => DngPaymentRequest::STATUS_CANCELLED]);
            }

            $registration->cancel($userId, $data['reason']);

            return $registration->fresh();
        });
    }
}
