<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;

final class DropCourseRegistrationAction
{
    public static function run(int $courseRegistrationId, int $campusId): void
    {
        DB::transaction(function () use ($courseRegistrationId, $campusId): void {
            $registration = CourseRegistration::query()
                ->with('semester')
                ->whereHas('courseOffering', fn ($query) => $query->where('campus_id', $campusId))
                ->lockForUpdate()
                ->findOrFail($courseRegistrationId);

            if (! $registration->canDrop()) {
                throw new \RuntimeException('Course cannot be dropped at this time');
            }

            $offering = CourseOffering::query()
                ->lockForUpdate()
                ->findOrFail($registration->course_offering_id);

            $registration->update([
                'registration_status' => 'dropped',
                'drop_date' => now(),
            ]);

            $offering->decrementEnrollment();
            $offering->updateStatus();
        });
    }
}
