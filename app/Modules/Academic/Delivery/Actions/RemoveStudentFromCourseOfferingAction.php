<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;

final class RemoveStudentFromCourseOfferingAction
{
    public static function run(int $courseRegistrationId): void
    {
        DB::transaction(fn (): mixed => self::remove($courseRegistrationId));
    }

    /** @param list<int> $courseRegistrationIds */
    public static function runMany(array $courseRegistrationIds): void
    {
        DB::transaction(function () use ($courseRegistrationIds): void {
            foreach (array_values(array_unique($courseRegistrationIds)) as $courseRegistrationId) {
                self::remove($courseRegistrationId);
            }
        });
    }

    private static function remove(int $courseRegistrationId): void
    {
        $registration = CourseRegistration::query()
            ->lockForUpdate()
            ->findOrFail($courseRegistrationId);
        $offering = CourseOffering::query()
            ->lockForUpdate()
            ->findOrFail($registration->course_offering_id);

        $registration->delete();

        if ((int) $offering->current_enrollment > 0) {
            $offering->decrement('current_enrollment');
            $offering->refresh();
            $offering->updateStatus();
        }
    }
}
