<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;

final class RemoveStudentFromCourseOfferingAction
{
    public static function run(int $courseRegistrationId, int $campusId): void
    {
        DB::transaction(fn (): mixed => self::remove($courseRegistrationId, $campusId));
    }

    /** @param list<int> $courseRegistrationIds */
    public static function runMany(array $courseRegistrationIds, int $campusId): void
    {
        DB::transaction(function () use ($courseRegistrationIds, $campusId): void {
            foreach (array_values(array_unique($courseRegistrationIds)) as $courseRegistrationId) {
                self::remove($courseRegistrationId, $campusId);
            }
        });
    }

    private static function remove(int $courseRegistrationId, int $campusId): void
    {
        $registration = CourseRegistration::query()
            ->whereHas('courseOffering', fn ($query) => $query->where('campus_id', $campusId))
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
