<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoEnrollRetakeCourseAction
{
    /**
     * Record paid retake registration and link an existing staff-created class registration.
     * Called after Finance settlement evidence confirms payment (DNG webhook / sync jobs).
     *
     * Settlement is resolved via ObligationSettlementReader — Academic never loads Finance models.
     */
    public static function handlePaymentConfirmed(int $registrationId): void
    {
        $registration = CourseRetakeRegistration::query()->find($registrationId);

        if (! $registration) {
            Log::warning('AutoEnrollRetakeCourse: Registration not found', [
                'registration_id' => $registrationId,
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

        DB::transaction(function () use ($registration): void {
            $settlement = app(AcademicObligationSettlement::class);
            if (! $settlement->isRetakeSettled($registration)) {
                Log::info('AutoEnrollRetakeCourse: Obligation is not settled, skipping', [
                    'registration_id' => $registration->id,
                    'settlement_state' => $settlement->forRetake($registration)->settlement_state,
                ]);

                return;
            }

            // Step 1: Transition to paid. Class placement remains staff-owned.
            if ($registration->status === CourseRetakeRegistration::STATUS_PAYMENT_PENDING) {
                $registration->transitionToPaid(now());
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
            }
        });
    }
}
