<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoEnrollRetakeCourseAction
{
    /**
     * Auto-enroll student when DNG payment is confirmed for a retake course charge.
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

        // Only process if currently at payment_pending
        if ($registration->status !== CourseRetakeRegistration::STATUS_PAYMENT_PENDING) {
            Log::info('AutoEnrollRetakeCourse: Registration not at payment_pending, skipping', [
                'registration_id' => $registration->id,
                'current_status' => $registration->status,
            ]);

            return;
        }

        DB::transaction(function () use ($registration) {
            // Step 1: Transition to paid
            $registration->transitionToPaid();

            // Step 2: Create CourseRegistration
            try {
                $unit = $registration->unit;
                $courseOffering = $registration->courseOffering;

                // Log warning if course is full but proceed anyway (admin_override)
                if ($courseOffering->current_enrollment >= $courseOffering->max_capacity) {
                    Log::warning('AutoEnrollRetakeCourse: CourseOffering is full, enrolling anyway (admin_override)', [
                        'registration_id' => $registration->id,
                        'course_offering_id' => $courseOffering->id,
                        'current_enrollment' => $courseOffering->current_enrollment,
                        'max_capacity' => $courseOffering->max_capacity,
                    ]);
                }

                $courseRegistration = CourseRegistration::create([
                    'student_id' => $registration->student_id,
                    'course_offering_id' => $registration->course_offering_id,
                    'semester_id' => $registration->semester_id,
                    'registration_status' => 'confirmed',
                    'registration_date' => now(),
                    'registration_method' => 'admin_override',
                    'is_retake' => true,
                    'attempt_number' => $registration->attempt_number,
                    'retake_fee' => $registration->retake_fee,
                    'is_retake_paid' => true,
                    'credit_points' => $unit->credit_points ?? 0,
                    'credit_hours' => $unit->credit_hours ?? 0,
                ]);

                // Increment enrollment count
                $courseOffering->incrementEnrollment();
                $courseOffering->updateStatus();

                // Step 3: Transition to enrolled
                $registration->transitionToEnrolled($courseRegistration->id);

                Log::info('AutoEnrollRetakeCourse: Student enrolled successfully', [
                    'registration_id' => $registration->id,
                    'course_registration_id' => $courseRegistration->id,
                    'student_id' => $registration->student_id,
                ]);
            } catch (\Throwable $e) {
                // Payment is already recorded (paid status). Log error for staff to handle manually.
                Log::error('AutoEnrollRetakeCourse: Failed to create CourseRegistration', [
                    'registration_id' => $registration->id,
                    'student_id' => $registration->student_id,
                    'error' => $e->getMessage(),
                ]);
                // Don't rethrow — payment status (paid) is separate from enrollment status
            }
        });
    }
}
