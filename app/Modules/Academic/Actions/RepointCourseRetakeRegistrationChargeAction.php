<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Point a course-retake registration (and its paid HL DNG bridge) at a different
 * active retake_fee charge for the same student.
 */
class RepointCourseRetakeRegistrationChargeAction
{
    private const HL = 'HL';

    private const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    /**
     * @return array{
     *   registration_id:int,
     *   previous_charge_id:int|null,
     *   finance_charge_id:int,
     *   dng_requests_updated:int
     * }
     */
    public function run(string $studentCode, int $financeChargeId): array
    {
        $student = Student::query()->where('student_id', $studentCode)->firstOrFail();

        $registration = CourseRetakeRegistration::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', CourseRetakeRegistration::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages([
                'student_code' => ['Sinh viên không có đăng ký học lại đang hoạt động.'],
            ]);
        }

        $charge = FinanceCharge::query()->findOrFail($financeChargeId);
        $this->assertTargetCharge($charge, (int) $student->id);

        return DB::transaction(function () use ($registration, $charge): array {
            $registration = CourseRetakeRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $charge = FinanceCharge::query()->lockForUpdate()->findOrFail($charge->id);

            $previousChargeId = $registration->finance_charge_id !== null
                ? (int) $registration->finance_charge_id
                : null;

            if ($previousChargeId === (int) $charge->id) {
                return [
                    'registration_id' => $registration->id,
                    'previous_charge_id' => $previousChargeId,
                    'finance_charge_id' => (int) $charge->id,
                    'dng_requests_updated' => 0,
                ];
            }

            if ($previousChargeId !== null) {
                $previous = FinanceCharge::query()->lockForUpdate()->find($previousChargeId);
                if ($previous && (int) $previous->source_id === (int) $registration->id) {
                    $previous->update([
                        'source_type' => null,
                        'source_id' => null,
                    ]);
                }
            }

            $registration->update(['finance_charge_id' => $charge->id]);

            $charge->update([
                'source_type' => CourseRetakeRegistration::class,
                'source_id' => $registration->id,
            ]);

            $dngUpdated = DngPaymentRequest::query()
                ->where('student_id', $registration->student_id)
                ->where('fee_type', self::HL)
                ->whereIn('status', self::PAID_DNG_STATUSES)
                ->when(
                    $previousChargeId !== null,
                    fn ($query) => $query->where('finance_charge_id', $previousChargeId),
                )
                ->update(['finance_charge_id' => $charge->id]);

            return [
                'registration_id' => $registration->id,
                'previous_charge_id' => $previousChargeId,
                'finance_charge_id' => (int) $charge->id,
                'dng_requests_updated' => $dngUpdated,
            ];
        });
    }

    private function assertTargetCharge(FinanceCharge $charge, int $studentId): void
    {
        if ((int) $charge->student_id !== $studentId) {
            throw ValidationException::withMessages([
                'finance_charge_id' => ['Charge không thuộc sinh viên này.'],
            ]);
        }

        if ($charge->charge_type !== FinanceCharge::TYPE_RETAKE_FEE) {
            throw ValidationException::withMessages([
                'finance_charge_id' => ['Charge phải là retake_fee.'],
            ]);
        }

        if ($charge->status !== FinanceCharge::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'finance_charge_id' => ['Charge phải đang active.'],
            ]);
        }
    }
}