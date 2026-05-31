<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\PaymentApplication;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoEnrollRetakeCourseAction
{
    /**
     * Record paid retake charges and link an existing staff-created class registration.
     * Called from DngWebhookService after payment bridge.
     */
    public static function handlePaymentConfirmed(FinanceCharge $charge): void
    {
        // Only handle retake course registrations
        if ($charge->source_type !== CourseRetakeRegistration::class) {
            return;
        }

        $registration = CourseRetakeRegistration::find($charge->source_id);

        if (! $registration) {
            Log::warning('AutoEnrollRetakeCourse: Registration not found', [
                'charge_id' => $charge->id,
                'source_id' => $charge->source_id,
            ]);

            return;
        }

        // Only process if payment is pending or already marked paid but enrollment failed.
        if (! in_array($registration->status, [
            CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
            CourseRetakeRegistration::STATUS_PAID,
            CourseRetakeRegistration::STATUS_ENROLLED,
        ], true)) {
            Log::info('AutoEnrollRetakeCourse: Registration is not payable/enrollable, skipping', [
                'registration_id' => $registration->id,
                'current_status' => $registration->status,
            ]);

            return;
        }

        DB::transaction(function () use ($registration) {
            $charge = $registration->financeCharge;
            if (! $charge || $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid) {
                Log::info('AutoEnrollRetakeCourse: Charge is not fully paid, skipping', [
                    'registration_id' => $registration->id,
                    'finance_charge_id' => $charge?->id,
                ]);

                return;
            }

            // Step 1: Transition to paid. Class placement remains staff-owned.
            if ($registration->status === CourseRetakeRegistration::STATUS_PAYMENT_PENDING) {
                $registration->transitionToPaid(self::resolvePaidAt($charge));
                $registration->refresh();
            }

            // Step 2: Link an existing staff-created CourseRegistration when present.
            try {
                $existingRegistration = CourseRegistration::query()
                    ->where('student_id', $registration->student_id)
                    ->where('course_offering_id', $registration->course_offering_id)
                    ->whereIn('registration_status', CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES)
                    ->orderByDesc('id')
                    ->first();

                if (! $existingRegistration) {
                    Log::info('AutoEnrollRetakeCourse: Payment recorded, waiting for staff class registration', [
                        'registration_id' => $registration->id,
                        'student_id' => $registration->student_id,
                        'course_offering_id' => $registration->course_offering_id,
                    ]);

                    return;
                }

                app(LinkPaidRetakeRegistrationToCourseRegistrationAction::class)->run($existingRegistration);

                Log::info('AutoEnrollRetakeCourse: Linked existing staff-created course registration', [
                    'registration_id' => $registration->id,
                    'course_registration_id' => $existingRegistration->id,
                    'student_id' => $registration->student_id,
                ]);
            } catch (\Throwable $e) {
                // Payment is already recorded (paid status). Log error for staff to handle manually.
                Log::error('AutoEnrollRetakeCourse: Failed to link CourseRegistration', [
                    'registration_id' => $registration->id,
                    'student_id' => $registration->student_id,
                    'error' => $e->getMessage(),
                ]);
                // Don't rethrow — payment status (paid) is separate from enrollment status
            }
        });
    }

    private static function resolvePaidAt(?FinanceCharge $charge): ?DateTimeInterface
    {
        if (! $charge) {
            return null;
        }

        $lineIds = $charge->invoiceLines()->pluck('id');
        if ($lineIds->isEmpty()) {
            return null;
        }

        return PaymentApplication::query()
            ->with('payment:id,paid_at')
            ->whereIn('invoice_line_id', $lineIds)
            ->where('amount', '>', 0)
            ->get()
            ->map(fn (PaymentApplication $application) => $application->payment?->paid_at)
            ->filter()
            ->sortBy(fn (DateTimeInterface $paidAt) => $paidAt->getTimestamp())
            ->last();
    }
}
