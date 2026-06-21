<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Modules\Finance\Actions\BridgePaidDngRequestsForChargeAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
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
                ->with('financeCharge')
                ->lockForUpdate()
                ->findOrFail($data['registration_id']);
            $userId = auth()->id();

            if (! $registration->isCancellable()) {
                throw new \RuntimeException(
                    "Không thể hủy đăng ký học lại ở trạng thái: {$registration->status}. Chỉ approved hoặc payment_pending mới được hủy."
                );
            }

            $charge = $registration->financeCharge;
            $bridgePaidDngRequestsForChargeAction = app(BridgePaidDngRequestsForChargeAction::class);
            $hasPaidDng = $bridgePaidDngRequestsForChargeAction->hasPaidDngForCharge($charge);
            if ($hasPaidDng && $charge !== null && ! $charge->is_fully_paid) {
                $bridgePaidDngRequestsForChargeAction->handle($charge);
                $charge = $charge->fresh();
                self::assertPaidDngCoveredCharge($charge);
            }

            $isPaid = $registration->hq_fee_status === CourseRetakeRegistration::HQ_FEE_PAID
                || ($charge !== null && $charge->status === FinanceCharge::STATUS_ACTIVE && $charge->is_fully_paid)
                || $hasPaidDng;

            if ($isPaid && $charge !== null && $charge->status === FinanceCharge::STATUS_ACTIVE) {
                app(VoidFinanceChargeAction::class)->handle(
                    $charge->id,
                    'retake_course_cancelled_paid_no_refund',
                    $userId,
                    false,
                );
                $charge = $charge->fresh();
            }

            // If payment_pending, void the finance charge and cancel DNG request
            if ($registration->status === CourseRetakeRegistration::STATUS_PAYMENT_PENDING && $charge !== null && ! $isPaid) {
                // Void charge
                app(VoidFinanceChargeAction::class)->handle(
                    $charge->id,
                    'retake_course_cancelled',
                    $userId,
                );

                self::cancelAwaitingDngRequestsForCharge($charge->id);
            }

            $registration->cancel(
                $userId,
                $data['reason'],
                $isPaid ? CourseRetakeRegistration::HQ_FEE_PAID : null,
            );

            return $registration->fresh();
        });
    }

    private static function assertPaidDngCoveredCharge(?FinanceCharge $charge): void
    {
        if ($charge === null || $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid) {
            throw new \RuntimeException(
                'DNG đã ghi nhận thanh toán phí học lại nhưng chưa thể phân bổ đủ vào khoản phí nội bộ. Vui lòng kiểm tra liên kết DNG/charge trước khi hủy.'
            );
        }
    }

    private static function cancelAwaitingDngRequestsForCharge(int $chargeId): int
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
}
