<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tạo FinanceCharge + invoice line cho đăng ký học lại — không tạo DNG payment request.
 * Dùng khi admin muốn ghi nhận khoản phí thủ công cho registration ở trạng thái approved (legacy path).
 * Registration tạo mới là nguồn Academic-owned; charge được tạo khi HQ xử lý.
 *
 * Delegates charge + invoice creation to CreateFinanceChargeAction so every charge
 * is always assigned to an invoice (finds existing draft or creates a new one).
 *
 * @see CreateRetakeCourseChargeAction cho flow có DNG.
 */
class CreateRetakeCourseChargeSimpleAction
{
    public function __construct(
        protected CreateFinanceChargeAction $createChargeAction,
    ) {}

    /**
     * @param  array{
     *   registration_id: int,
     *   charge_type: string,
     *   amount: float,
     *   description: string,
     * }  $data
     */
    public function handle(array $data): CourseRetakeRegistration
    {
        return DB::transaction(function () use ($data) {
            $registration = CourseRetakeRegistration::lockForUpdate()->findOrFail($data['registration_id']);

            if ($registration->status !== CourseRetakeRegistration::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Chỉ có thể tạo charge cho đăng ký ở trạng thái approved.'],
                ]);
            }

            $charge = FinanceCharge::query()
                ->where('source_type', CourseRetakeRegistration::class)
                ->where('source_id', $registration->id)
                ->where('charge_type', $data['charge_type'])
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->first()
                ?? $this->createChargeAction->handle([
                    'student_id' => $registration->student_id,
                    'semester_id' => $registration->charge_semester_id ?? $registration->semester_id,
                    'charge_type' => $data['charge_type'],
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                    'source_type' => CourseRetakeRegistration::class,
                    'source_id' => $registration->id,
                    'created_by_user_id' => auth()->id(),
                ]);

            $registration->transitionToPaymentPending($charge->id, auth()->id());

            return $registration->fresh();
        });
    }
}
