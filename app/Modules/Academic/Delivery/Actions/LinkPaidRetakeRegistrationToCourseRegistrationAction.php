<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LinkPaidRetakeRegistrationToCourseRegistrationAction
{
    public function __construct(
        private readonly AcademicObligationSettlement $obligationSettlement,
    ) {}

    public function run(CourseRegistration $courseRegistration): ?CourseRetakeRegistration
    {
        $courseRegistration->loadMissing(['courseOffering.unit']);

        if (! $this->canLinkCourseRegistration($courseRegistration)) {
            return null;
        }

        return DB::transaction(function () use ($courseRegistration): ?CourseRetakeRegistration {
            $courseRegistration = CourseRegistration::query()
                ->with(['courseOffering.unit'])
                ->lockForUpdate()
                ->find($courseRegistration->id);

            if (! $courseRegistration || ! $this->canLinkCourseRegistration($courseRegistration)) {
                return null;
            }

            $registration = $this->findCandidate($courseRegistration);
            if (! $registration) {
                return null;
            }

            if (! $this->obligationSettlement->isRetakeSettled($registration)) {
                return null;
            }

            if ($registration->status === CourseRetakeRegistration::STATUS_PAYMENT_PENDING) {
                $registration->transitionToPaid(now());
                $registration->refresh();
            }

            $unit = $registration->unit;
            $courseRegistration->update([
                'registration_status' => 'confirmed',
                'registration_date' => $courseRegistration->registration_date ?? now(),
                'registration_method' => $courseRegistration->registration_method ?? 'admin_override',
                'is_retake' => true,
                'attempt_number' => $registration->attempt_number,
                'retake_fee' => $registration->retake_fee,
                'is_retake_paid' => 'yes',
                'credit_points' => (float) $courseRegistration->credit_points > 0
                    ? $courseRegistration->credit_points
                    : ($unit->credit_points ?? 0),
                'credit_hours' => (float) $courseRegistration->credit_hours > 0
                    ? $courseRegistration->credit_hours
                    : ($unit->credit_hours ?? 0),
            ]);

            $registration->linkToCourseRegistration($courseRegistration->id);

            Log::info('Linked paid retake registration to existing course registration', [
                'registration_id' => $registration->id,
                'course_registration_id' => $courseRegistration->id,
                'student_id' => $registration->student_id,
            ]);

            return $registration->fresh();
        });
    }

    private function canLinkCourseRegistration(CourseRegistration $courseRegistration): bool
    {
        return in_array(
            $courseRegistration->registration_status,
            CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES,
            true,
        ) && $courseRegistration->courseOffering !== null;
    }

    private function findCandidate(CourseRegistration $courseRegistration): ?CourseRetakeRegistration
    {
        $courseOffering = $courseRegistration->courseOffering;
        if (! $courseOffering) {
            return null;
        }

        return CourseRetakeRegistration::query()
            ->with(['courseRegistration', 'unit'])
            ->lockForUpdate()
            ->where('student_id', $courseRegistration->student_id)
            ->where('semester_id', $courseRegistration->semester_id)
            ->where('unit_id', $courseOffering->unit_id)
            ->where('course_offering_id', $courseRegistration->course_offering_id)
            ->whereIn('status', [
                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
                CourseRetakeRegistration::STATUS_PAID,
                CourseRetakeRegistration::STATUS_ENROLLED,
            ])
            ->orderByDesc('id')
            ->get()
            ->first(fn (CourseRetakeRegistration $registration): bool => $this->canRelink($registration, $courseRegistration));
    }

    private function canRelink(CourseRetakeRegistration $registration, CourseRegistration $courseRegistration): bool
    {
        if ($registration->course_registration_id === null) {
            return true;
        }

        if ($registration->course_registration_id === $courseRegistration->id) {
            return true;
        }

        $linkedRegistration = $registration->courseRegistration;
        if (! $linkedRegistration) {
            return true;
        }

        return ! in_array(
            $linkedRegistration->registration_status,
            CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES,
            true,
        );
    }
}
