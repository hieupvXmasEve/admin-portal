<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateRetakeCourseChargeAction
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngCampusCodeResolver $dngCampusCodeResolver,
    ) {}

    /**
     * Create FinanceCharge + DNG request for an approved retake course registration.
     *
     * @param  array{
     *   registration_id: int,
     *   amount?: float|null,
     *   payment_deadline?: string|null,
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

            $student = $registration->student;
            $unit = $registration->unit;
            $amount = $data['amount'] ?? $registration->retake_fee;
            $paymentDeadline = $data['payment_deadline'] ?? null;
            $userId = auth()->id();

            // Create FinanceCharge
            $charge = FinanceCharge::create([
                'student_id' => $registration->student_id,
                'semester_id' => $registration->semester_id,
                'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
                'amount' => $amount,
                'description' => "Phí học lại: {$unit->code} - {$unit->name}",
                'effective_at' => now(),
                'status' => FinanceCharge::STATUS_ACTIVE,
                'source_type' => CourseRetakeRegistration::class,
                'source_id' => $registration->id,
                'created_by_user_id' => $userId,
            ]);

            // Create DNG payment request
            $campusCode = $this->dngCampusCodeResolver->requireForStudent($student);

            $this->dngPaymentService->createAndPush($student, [
                'campus_code' => $campusCode,
                'student_code' => $student->student_id,
                'fee_type' => 'retake_fee',
                'description' => "Phí học lại: {$unit->code} - {$unit->name}",
                'semester_id' => $registration->semester_id,
                'due_date' => $paymentDeadline,
                'item_id' => Str::uuid()->toString(),
                'amount' => $amount,
                'type' => 'retake_fee',
                'student_name' => $student->full_name,
                'email' => $student->email ?? '',
                'estimate_time' => $paymentDeadline ?? now()->addDays(30)->format('Y-m-d'),
                'student_address' => $student->address ?? $student->current_address_line ?? 'N/A',
                'cccd' => $student->national_id ?? '',
            ]);

            // Transition registration to payment_pending
            $registration->transitionToPaymentPending($charge->id, $userId);

            // Update payment deadline on registration
            if ($paymentDeadline) {
                $registration->update(['payment_deadline' => $paymentDeadline]);
            }

            return $registration->fresh();
        });
    }
}
