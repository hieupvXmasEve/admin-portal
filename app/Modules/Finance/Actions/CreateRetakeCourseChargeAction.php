<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateRetakeCourseChargeAction
{
    public function __construct(
        protected CreateFinanceChargeAction $createChargeAction,
        protected DngPaymentService $dngPaymentService,
        protected DngCampusCodeResolver $dngCampusCodeResolver,
    ) {}

    /**
     * Create a single aggregate DNG payment request for all payment_pending retake
     * registrations of a given student (one HL charge per unit).
     *
     * Behavior:
     * - Collects all CourseRetakeRegistrations in STATUS_PAYMENT_PENDING for the student.
     * - Legacy path (STATUS_APPROVED): creates the FinanceCharge first, transitions to payment_pending.
     * - Sums all charge amounts into a single DNG request (one HL record per student).
     * - Inserts a dng_payment_request_charges pivot row per charge for precise allocation on webhook.
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
            // Lock and validate the triggering registration
            $registration = CourseRetakeRegistration::lockForUpdate()->findOrFail($data['registration_id']);

            $allowedStatuses = [
                CourseRetakeRegistration::STATUS_APPROVED,
                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
            ];

            if (! in_array($registration->status, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Chỉ có thể tạo yêu cầu DNG cho đăng ký ở trạng thái approved hoặc payment_pending.'],
                ]);
            }

            $student = $registration->student;
            $userId = auth()->id();
            $paymentDeadline = $data['payment_deadline'] ?? null;

            // Legacy path: registration still approved → create charge + transition
            if ($registration->status === CourseRetakeRegistration::STATUS_APPROVED) {
                $unit = $registration->unit;
                $amount = $data['amount'] ?? $registration->retake_fee;

                $charge = $this->findActiveSourceCharge($registration)
                    ?? $this->createChargeAction->handle([
                        'student_id' => $registration->student_id,
                        'semester_id' => $registration->charge_semester_id ?? $registration->semester_id,
                        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
                        'amount' => $amount,
                        'description' => "Phí học lại: {$unit->code} - {$unit->name}",
                        'source_type' => CourseRetakeRegistration::class,
                        'source_id' => $registration->id,
                        'created_by_user_id' => $userId,
                        'due_date' => $paymentDeadline,
                    ]);

                $registration->transitionToPaymentPending($charge->id, $userId);
                $registration->refresh();
            }

            // Collect all payment_pending registrations for this student (including this one)
            // Lock them all to prevent concurrent DNG creation
            $pendingRegistrations = CourseRetakeRegistration::query()
                ->where('student_id', $registration->student_id)
                ->where('status', CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
                ->lockForUpdate()
                ->get();

            // Resolve finance_charge_id for each registration
            /** @var Collection<int, array{registration: CourseRetakeRegistration, charge: FinanceCharge}> */
            $chargeItems = $pendingRegistrations
                ->filter(fn (CourseRetakeRegistration $r) => $r->finance_charge_id !== null)
                ->map(function (CourseRetakeRegistration $r) {
                    $charge = FinanceCharge::find($r->finance_charge_id);

                    return $charge ? ['registration' => $r, 'charge' => $charge] : null;
                })
                ->filter()
                ->values();

            if ($chargeItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Không tìm thấy khoản phí hợp lệ để tạo DNG.'],
                ]);
            }

            $campusCode = $this->dngCampusCodeResolver->requireForStudent($student);
            $dngFeeType = DngFeeTypeOptions::fromChargeType(FinanceCharge::TYPE_RETAKE_FEE);

            // Aggregate total amount across all pending charges
            $totalAmount = $chargeItems->sum(fn ($item) => (float) $item['charge']->amount);

            // Build description listing all units
            $unitCodes = $chargeItems
                ->map(fn ($item) => $item['registration']->unit?->code ?? "#{$item['registration']->unit_id}")
                ->join(', ');
            $description = "Phí học lại: {$unitCodes}";

            // Create a single aggregate DNG payment request
            $dngRequest = $this->dngPaymentService->createAndPush($student, [
                'campus_code' => $campusCode,
                'student_code' => $student->student_id,
                'fee_type' => $dngFeeType,
                'description' => $description,
                'semester_id' => $registration->semester_id,
                'due_date' => $paymentDeadline,
                'item_id' => Str::uuid()->toString(),
                'amount' => $totalAmount,
                'type' => $dngFeeType,
                'student_name' => $student->full_name,
                'email' => $student->email ?? '',
                'estimate_time' => $paymentDeadline ?? now()->addDays(30)->format('Y-m-d'),
                'student_address' => $student->address ?? $student->current_address_line ?? 'N/A',
                'cccd' => $student->national_id ?? '',
                // finance_charge_id left null — multi-charge link is via chargeLinks pivot
                'finance_charge_id' => null,
            ]);

            // Insert pivot rows: one per charge, recording the exact amount per charge
            foreach ($chargeItems as $item) {
                DngPaymentRequestCharge::create([
                    'dng_payment_request_id' => $dngRequest->id,
                    'finance_charge_id' => $item['charge']->id,
                    'amount' => $item['charge']->amount,
                ]);
            }

            // Update payment deadline on all covered registrations
            if ($paymentDeadline) {
                $pendingRegistrations->each(
                    fn (CourseRetakeRegistration $r) => $r->update(['payment_deadline' => $paymentDeadline])
                );
            }

            return $registration->fresh();
        });
    }

    private function findActiveSourceCharge(CourseRetakeRegistration $registration): ?FinanceCharge
    {
        return FinanceCharge::query()
            ->where('source_type', CourseRetakeRegistration::class)
            ->where('source_id', $registration->id)
            ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->first();
    }
}
